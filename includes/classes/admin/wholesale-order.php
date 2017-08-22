<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\User, Zao\ZaoWooCommerce_Wholesale\Base;
use WC_Order;

/**
 * The order admin interface for wholesale orders.
 */
class Wholesale_Order extends Base {
	protected static $is_wholesale = null;
	protected static $is_edit_mode = null;
	protected $products            = array();
	public $quantity_management    = null;

	public function __construct() {
		if ( self::is_wholesale_context() ) {
			$this->quantity_management = new Quantity_Management;
		}
	}

	public function init() {
		if ( self::is_wholesale_context() ) {

			$this->quantity_management->init();

			$order_type_object = get_post_type_object( sanitize_text_field( 'shop_order' ) );
			$order_type_object->labels->add_new_item = __( 'Add new wholesale order', 'zwoowh' );

			if ( self::is_wholesale_edit_context() ) {
				$order_type_object->labels->edit_item = __( 'Edit wholesale order', 'zwoowh' );
				add_action( 'admin_footer', array( $this, 'add_wholesale_order_button' ) );
			}

			// TODO: Update shipping method with Shipstation Method
			// TODO: Also set via set_shipping_total()
			// add_filter( 'woocommerce_order_shipping_method' )

			add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'filter_wholesale_pricing' ), 10, 3 );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_wholesale_order_meta' ) );
			add_filter( 'admin_body_class'     , array( $this, 'filter_admin_body_class' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'admin_footer'         , array( $this, 'add_app' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_help' ) );
			add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_shipstation_rates_button' ) );

			add_filter( 'woocommerce_shipstation_export_custom_field_2', array( $this, 'add_wholesale_custom_field_to_shipstation_order' ) );
			add_action( 'wp_ajax_woocommerce_json_search_customers', array( $this, 'maybe_limit_user_search_to_wholesalers' ), 5 );
			add_action( 'wp_ajax_get_shipstation_shipping_rates', array( $this, 'ajax_shipstation_rates' ) );
		} else {
			add_action( 'admin_head', array( $this, 'maybe_add_wholesale_order_button' ), 9999 );
		}
	}

	public function add_shipstation_rates_button( $order ) {
	 if ( $this->can_be_shipped( $order ) ) : ?>
		<span class="shipstation-spinner spinner" style="float: none; vertical-align: top;"></span><button type="button" id="get_shipstation_rates" class="button button-primary get-rates"><?php _e( 'Get Shipstation Rates', 'zwoowh' ); ?></button>
	<?php endif;
	}

	public function add_wholesale_custom_field_to_shipstation_order() {
		return 'is_wholesale_order';
	}

	public function ajax_shipstation_rates() {

		if ( ! isset( $_POST['order_id'] ) ) {
			wp_send_json_error();
		}

		$order = wc_get_order( $_POST['order_id'] );

		$args = array(
			'order_id'       => $_POST['order_id'],
			'fromPostalCode' => apply_filters( 'zwoowh_base_shipping_zip_code', '' ), // TODO: Expose as setting?
			'toCountry'      => $order->get_shipping_country(),
			'toPostalCode'   => $order->get_shipping_postcode(),
			'weight'         => array( 'value' => $this->get_order_weight( $order ), 'units' => 'ounces' ),
		);

		$rates = $this->get_rates( $args );

		if ( ! empty( $rates ) ) {
			wp_send_json_success( $rates );
		} else {
			wp_send_json_error( $rates );
		}
	}

	/**
	 * Gets order weight.
	 *
	 * @todo In Customizations, modify order weight to include virtual printed patterns if order is international.
	 * @todo In order to do the above, we need a custom weight field exposed.
	 *
	 * @param  WC_Order $order [description]
	 * @return [type]          [description]
	 */
	public function get_order_weight( WC_Order $order ) {
		$weight = 0;

		foreach ( $order->get_items() as $item ) {

			if ( $item['product_id'] > 0 ) {

				$_product = $order->get_product_from_item( $item );

				if ( ! $_product->is_virtual() ) {

					$weight += $_product->get_weight() * $item['qty'];

				}

			}

		}

		return apply_filters( 'zwoowh_get_order_weight', $weight, $order );

	}


	public function can_be_shipped( WC_Order $order ) {
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

	/**
	 * Gets Shipping Rates from Shipstation for a given order.
	 *
	 * @return [type] [description]
	 */
	public function get_rates( Array $args = array() ) {

		$order = $args['order_id'];

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
				'Authorization' => 'Basic ' . base64_encode( ZWOOWH_SHIPSTATION_API_KEY . ':' . ZWOOWH_SHIPSTATION_API_SECRET ),
			),
			'body'    => $body
		);

		$response = wp_remote_post( 'https://ssapi.shipstation.com/shipments/getrates', $args );

		$status   = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status ) {
			$rates = json_decode( wp_remote_retrieve_body( $response ) );
		} else {
			$rates = array();
		}

		return apply_filters( 'zwoowh_get_rates', $rates, $args, $response );
	}

	public function filter_admin_body_class( $body_class = '' ) {
		$body_class = trim( $body_class ) . ' is-wholesale-order ' . ( self::is_wholesale_edit_context() ? 'edit-wholesale-order' : 'init-wholesale-order' );
		return $body_class;
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/css/zao-woocommerce-wholesale{$min}.css", array(), ZWOOWH_VERSION );
		wp_register_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
		wp_register_script( 'zao-woocommerce-wholesale-shipstation', ZWOOWH_URL . "assets/js/shipstation{$min}.js", array(), ZWOOWH_VERSION, true );
		add_action( 'admin_footer', array( $this, 'enqueue_and_localize_data' ), 12 );
	}

	public function enqueue_and_localize_data() {
		wp_enqueue_script( 'zao-woocommerce-wholesale' );
		wp_enqueue_script( 'zao-woocommerce-wholesale-shipstation' );
		$data = apply_filters( 'zao_woocommerce_wholesale_l10n', array(
			'rest_url'          => rest_url(),
			'placeholderImgSrc' => wc_placeholder_img_src(),
			'rest_nonce'        => wp_create_nonce( 'wp_rest' ),
			'is_wholesale'      => wp_create_nonce( __FILE__ ),
			'replaceDropdown'   => self::should_replace_dropdown(),
			'is_edit_mode'      => self::is_wholesale_edit_context(),
			'allProducts'       => array(),
			'variableProducts'  => array(),
			'columns'           => array(
				array(
					'name' => 'img',
					'title' => __( 'Thumb', 'zwoowh' ),
					'filter' => false,
				),
				array(
					'name' => 'sku',
					'title' => __( 'SKU', 'zwoowh' ),
				),
				array(
					'name' => 'name',
					'title' => __( 'Name/Variation', 'zwoowh' ),
				),
				array(
					'name' => 'price',
					'title' => __( 'Wholesale Price', 'zwoowh' ),
				),
				array(
					'name' => 'qty',
					'title' => __( 'Quantity', 'zwoowh' ),
					'filter' => false,
				),
				array(
					'name' => 'categories',
					'title' => __( 'Categories', 'zwoowh' ),
				),
			),
			'searchParams' => array(
				'name',
				'parent',
				'sku',
				'categories',
			),
			'productFields' => array(
				'id',
				'img:40',
				'sku',
				'name',
				'wholesale_price',
				'price',
				'variations',
				'type',
				'manage_stock',
				'stock_quantity',
				'in_stock',
				'editlink',
				'category_names',
			),
			'allCategories' => get_terms( 'product_cat', array( 'fields' => 'names', 'update_term_meta_cache' => false ) ),
			'l10n' => array(
				'somethingWrong'       => __( 'Something went wrong and we were not able to retrieve the wholesale products.', 'zwoowh' ),
				'msgReceived'          => __( 'The error found:', 'zwoowh' ),
				'noStockTitle'         => __( 'This item is out of stock.', 'zwoowh' ),
				'addProductsBtn'       => __( 'Add Products', 'zwoowh' ),
				'clearBtn'             => __( 'Clear', 'zwoowh' ),
				'selectProductsTitle'  => __( 'Select Products', 'zwoowh' ),
				'variantProductsTitle' => __( 'Variant Products', 'zwoowh' ),
				'customTaxName'        => '',
				'categoryTitle'        => __( 'Categories', 'zwoowh' ),
				'searchPlaceholder'    => __( 'Filter products by id, sku, name, parent, price, etc', 'zwoowh' ),
				'plsWait'              => __( 'Loading Products. Please try again in a second.', 'zwoowh' ),
				'closeBtn'             => __( 'Close product selector', 'zwoowh' ),
				'insertBtn'            => __( 'Insert', 'zwoowh' ),
				'origPrice'            => __( 'Original Price: $%d', 'zwoowh' ),
			),
		) );

		wp_localize_script( 'zao-woocommerce-wholesale', 'ZWOOWH', $data );
	}

	public static function should_replace_dropdown() {
		static $replace_dropdown = null;
		if ( null === $replace_dropdown ) {
			$users = User::get_wholesale_users();

			// If we have less than 500 wholesale users, let's create a snappier dropdown that doesn't require ajax searches.
			$replace_dropdown =  empty( $users ) || count( $users ) < 500;

			// But allow overriding via filter.
			$replace_dropdown = apply_filters( 'zao_woocommerce_wholesale_replace_search_dropdown', $replace_dropdown );
		}

		return $replace_dropdown;
	}

	public function add_help( $order ) {
		$users = User::get_wholesale_users();

		// If we have less than 500 wholesale users, let's create a snappier dropdown that doesn't require ajax searches.
		if ( self::should_replace_dropdown() ) {

			$user_id = '';
			if ( $order->get_user_id() ) {
				$user_id = absint( $order->get_user_id() );
			}

			?>
			<script>
				var select = document.getElementById( 'customer_user' );
				select.parentElement.removeChild( select );
			</script>
			<select class="wc-wholesale-search" id="customer_user" name="customer_user" data-placeholder="<?php esc_attr_e( 'Search for Wholesaler', 'zwoowh' ); ?>" data-allow_clear="true" style="width:99%;">
				<option value="" <?php selected( ! $user_id ); ?>><?php esc_attr_e( 'Select Wholesaler', 'zwoowh' ); ?></option>
				<?php foreach ( $users as $user ) {
					/* translators: 1: user display name 2: user ID 3: user email */
					$user_string = sprintf(
						esc_html__( '%1$s (#%2$s - %3$s)', 'zwoowh' ),
						$user->display_name,
						absint( $user->ID ),
						$user->user_email
					); ?>

				<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id === absint( $user->ID ) ); ?>><?php echo htmlspecialchars( $user_string ); ?></option>

				<?php } ?>
			</select>
			<?php

		} else { ?>
			<script>
				var select = document.getElementById( 'customer_user' );
				select.setAttribute( 'data-placeholder', '<?php echo esc_js( __( 'Search for Wholesaler', 'zwoowh' ) ); ?>' );
				select.style.width = '99%';
			</script>
		<?php }
		?>
		<input type="hidden" name="is_wholesale" value="<?php echo wp_create_nonce( __FILE__ ); ?>" />
		<?php
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

	public function maybe_limit_user_search_to_wholesalers() {
		add_action( 'pre_get_users', array( $this, 'limit_user_search_to_wholesalers' ) );
	}

	/**
	 * Filters product price in admin when adding items to the cart.
	 *
	 * @param  [type] $price    [description]
	 * @param  [type] $quantity [description]
	 * @param  [type] $product  [description]
	 * @return [type]           [description]
	 */
	public function filter_wholesale_pricing( $price, $quantity, $product ) {
		if ( ! is_admin() || ! current_user_can( 'publish_shop_orders' ) ) {
			return $price;
		}

		if ( ! doing_action( 'wp_ajax_woocommerce_add_order_item' ) ) {
			return $price;
		}

		// Margins are currently set on parent product, not per-variation
		if ( 'variation' === $product->get_type() ) {
			$_product = wc_get_product( $product->get_parent_id() );
			$margin   = $_product->get_meta( 'wholesale_margin' );
		} else {
			$margin = $product->get_meta( 'wholesale_margin' );
		}

		if ( $margin ) {
			$price = round( $price / $margin, 2 );
		}

		return $price;
	}

	public function maybe_add_wholesale_order_button() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if (
			is_object( $screen )
			&& in_array( $screen->base, array( 'edit', 'post' ) )
			&& 'shop_order' === $screen->post_type
		) {
			add_action( 'admin_footer', array( $this, 'add_wholesale_order_button' ) );
		}
	}

	public function add_wholesale_order_button() {
		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( 'a.page-title-action' ).after( '<a href="<?php echo esc_url( Menu::new_wholesale_order_url() ); ?>" class="page-title-action alignright"><?php esc_html_e( 'Add wholesale order', 'zwoowh' ); ?></a>' );
			});
		</script>
		<?php
	}

	public function save_wholesale_order_meta( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order ) {
			$order->update_meta_data( 'is_wholesale_order', true );
			$order->save_meta_data();
		}
	}

	public function limit_user_search_to_wholesalers( $query ) {
		$query->set( 'role', User::ROLE );

		$users = User::get_wholesale_users();

		// Because a huge $wpdb->users.ID IN ($ids) query is probably not better for performance,
		// We'll only set the 'include' parameter for less than 300 wholesale users.
		if ( ! empty( $users ) && count( $users ) < 300 ) {
			$query->set( 'include', wp_list_pluck( $users, 'ID' ) );
		}
	}

	public static function remove_dynamic_pricing_if_wholesale( $eligible ) {
		return $eligible && ! self::is_wholesale_context();
	}

	public static function is_wholesale_context() {
		if ( null === self::$is_wholesale ) {
			self::set_is_wholesale_and_edit_mode();
		}

		return self::$is_wholesale;
	}

	public static function is_wholesale_edit_context() {
		if ( null === self::$is_edit_mode ) {
			self::set_is_wholesale_and_edit_mode();
		}

		return self::$is_edit_mode;
	}

	protected static function set_is_wholesale_and_edit_mode() {
		global $pagenow;

		self::$is_edit_mode = (
			'post.php' === $pagenow
			&& isset( $_GET['post'] )
			&& 'shop_order' === get_post_type( $_GET['post'] )
			&& ( $order = get_post( absint( $_GET['post'] ) ) )
			&& $order->is_wholesale_order
		);

		self::$is_wholesale = self::$is_edit_mode || (
			'post-new.php' === $pagenow
			&& isset( $_GET['post_type'], $_GET['wholesale'] )
			&& 'shop_order' === $_GET['post_type']
		) || (
			isset( $_REQUEST['is_wholesale'] )
			&& wp_verify_nonce( $_REQUEST['is_wholesale'], __FILE__ )
		);
	}

}
