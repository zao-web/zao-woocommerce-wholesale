<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base, WC_Order;

/**
 * Adding Shipstation interface (if key/secret is defined)
 */
class Shipstation extends Base {
	const GET_RATES_URL = 'https://ssapi.shipstation.com/shipments/getrates';

	public function __construct() {}

	public function init() {
		if ( self::shipstation_keys() ) {
			add_filter( 'woocommerce_order_shipping_method', array( __CLASS__, 'maybe_filter_shipping_method' ), 10, 2 );
			add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'add_shipstation_rates_button' ) );
		}
		add_action( 'wp_ajax_get_shipstation_rates', array( __CLASS__, 'ajax_get_order_rates' ) );
		add_action( 'wp_ajax_set_shipstation_rates' , array( __CLASS__, 'ajax_set_order_rates' ) );
		add_filter( 'woocommerce_shipstation_export_custom_field_2', array( '\\Zao\\ZaoWooCommerce_Wholesale\\Admin\\Wholesale_Order', 'get_wholesale_custom_field' ) );
	}

	public static function maybe_filter_shipping_method( $methods, $order ) {

		$ss_method = $order->get_meta( 'shipstation_method' );

		if ( empty( $ss_method ) ) {
			return $methods;
		}

		return $ss_method;
	}

	public static function add_shipstation_rates_button( $order ) {
		if ( self::can_be_shipped( $order ) ) : ?>
		<span class="shipstation-spinner spinner" style="float: none; vertical-align: top;"></span><button type="button" id="get_shipstation_rates" class="button button-primary get-rates" style="margin-left:1em"><?php _e( 'Get Shipstation Rates', 'zwoowh' ); ?></button>
		<?php endif;
	}

	public function register_script() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( 'zao-woocommerce-wholesale-shipstation', ZWOOWH_URL . "assets/js/shipstation{$min}.js", array( 'zao-woocommerce-wholesale' ), ZWOOWH_VERSION, true );
	}

	public function enqueue_script() {
		wp_enqueue_script( 'zao-woocommerce-wholesale-shipstation' );
	}

	public static function ajax_get_order_rates() {

		if ( ! isset( $_POST['order_id'] ) ) {
			wp_send_json_error();
		}

		$order = wc_get_order( absint( $_POST['order_id'] ) );

		if ( ! $order ) {
			wp_send_json_error();
		}

		$rates = self::get_order_rates( $order );

		if ( is_wp_error( $rates ) ) {
			wp_send_json_error( array( 'msg' => $rates->get_error_message() ) );
		}

		if ( ! empty( $rates ) ) {
			wp_send_json_success( $rates );
		}

		wp_send_json_error( $rates );
	}

	/**
	 * Gets Shipping Rates from Shipstation for a given order.
	 *
	 * @return [type] [description]
	 */
	public static function get_order_rates( WC_Order $order ) {

		$keys     = self::shipstation_keys();
		$rates    = array();
		$response = null;

		if ( $keys ) {
			$args = array(
				'order_id'       => $order->get_id(),
				'fromPostalCode' => apply_filters( 'zwoowh_base_shipping_zip_code', '' ), // TODO: Expose as setting?
				'toCountry'      => $order->get_shipping_country(),
				'toPostalCode'   => $order->get_shipping_postcode(),
				'weight'         => array( 'value' => self::get_order_weight( $order ), 'units' => 'ounces' ),
			);

			$body  = apply_filters( 'zwoowh_get_wholesale_rates_args', wp_parse_args( $args, array(
				'carrierCode'    => 'stamps_com', // Required
				'serviceCode'    => '',
				'packageCode'    => '',
				'fromPostalCode' => '', // Required
				'toState'        => '', // Required if UPS
				'toCountry'      => '', // Required
				'toPostalCode'   => '', // Required
				'toCity'         => '',
				'weight'         => '', // Required, as a Weight object
			), $order ) );

			$args = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $keys['key'] . ':' . $keys['secret'] ),
				),
				'body' => $body
			);

			$full_response = wp_remote_post( self::GET_RATES_URL, $args );
			$response      = json_decode( wp_remote_retrieve_body( $full_response ) );
			$status        = wp_remote_retrieve_response_code( $full_response );

			if ( 200 === $status ) {
				$rates = $response;
			} elseif ( isset( $response->Message ) ) {
				$rates = new \WP_Error( 'zwoowh_get_rates_error', self::get_error_text( $response ), $full_response );
			}
		}

		return apply_filters( 'zwoowh_get_rates', $rates, $args, $response );
	}

	protected static function get_error_text( $response ) {
		$error = $response->Message;

		if ( isset( $response->ModelState ) && is_object( $response->ModelState ) ) {
			$more_info = array();

			foreach ( get_object_vars( $response->ModelState ) as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					foreach ( $value as $_key => $_value ) {
						$more_info[] = print_r( $_value, true ) . ' (' . $key . ')';
					}
				} else {
					$more_info[] = print_r( $value, true );
				}
			}

			if ( ! empty( $more_info ) ) {
				$error .= ' ' . implode( '; ', $more_info );
			}
		}

		return $error;
	}

	/**
	 * Gets order weight.
	 *
	 * @todo abstract customization to plugin.
	 *
	 * @param  WC_Order $order [description]
	 * @return [type]          [description]
	 */
	public static function get_order_weight( WC_Order $order ) {
		$weight = 0;

		$shipping_country = $order->get_shipping_country();

		$is_international = ! empty( $shipping_country ) && 'US' !== $shipping_country;

		foreach ( $order->get_items() as $item ) {

			if ( $item['product_id'] > 0 ) {

				$_product = $order->get_product_from_item( $item );
				$product_has_virtual_weight = $_product->get_meta( 'virtual_product_weight' );

				if ( ! $_product->is_virtual() ) {

					$weight += $_product->get_weight() * $item['qty'];

				} else if ( $is_international && $product_has_virtual_weight  ) {

					$weight += $product_has_virtual_weight * $item['qty'];

				}

			}

		}

		return apply_filters( 'zwoowh_get_order_weight', $weight, $order );
	}

	public static function ajax_set_order_rates() {

		if ( ! isset( $_POST['order_id'] ) ) {
			wp_send_json_error();
		}

		$order = wc_get_order( $_POST['order_id'] );

		if ( ! $order ) {
			wp_send_json_error();
		}

		$method_bits = explode( '-', $_POST['method'] );

		array_pop( $method_bits );

		$method    = sanitize_text_field( implode( '-', $method_bits ) );
		$method_id = sanitize_text_field( $_POST['value'] );

		self::set_order_rates( $order, $_POST['price'], $method, $method_id );

		wp_send_json_success( $_POST );
	}

	public static function set_order_rates( WC_Order $order, $price, $method, $method_id ) {

		// Set Shipping total
		$shipping_item = new \WC_Order_Item_Shipping();

		$shipping_item->set_name( $method );
		$shipping_item->set_method_id( $method_id );
		$shipping_item->set_total( floatval( $price ) );

		$order->add_item( $shipping_item );

		$order->update_meta_data( 'shipstation_method', $method );

		$order->save_meta_data();
		$order->calculate_totals();

		return $order;
	}

	public static function can_be_shipped( WC_Order $order ) {
		$needs_shipping = false;

		foreach ( $order->get_items() as $item ) {

			if ( $item['product_id'] > 0 ) {

				$_product = $order->get_product_from_item( $item );

				if ( $_product->needs_shipping() ) {

					$needs_shipping = true;
					break;
				}
			}
		}

		return apply_filters( 'zwoowh_order_can_be_shipped', $needs_shipping, $order );
	}

	public static function shipstation_keys() {
		return defined( 'ZWOOWH_SHIPSTATION_API_KEY' ) && defined( 'ZWOOWH_SHIPSTATION_API_SECRET' )
			? array(
				'key'    =>  ZWOOWH_SHIPSTATION_API_KEY,
				'secret' => ZWOOWH_SHIPSTATION_API_SECRET,
			) : false;
	}

}
