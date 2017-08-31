<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * Handling quantities for the wholesale management interface.
 */
class Inventory_Management extends Base {
	protected static $quantities = array();
	public function __construct() {}

	public function init() {

		// For wholesale orders, do not reduce stock levels for items
		// within an order when a payment is complete.
		// This requires clicking the button to do so.
		add_filter( 'woocommerce_payment_complete_reduce_order_stock', array( __CLASS__, 'disable_payment_complete_stock_reduction_for_wholesale' ), 10, 2 );
		add_filter( 'zao_woocommerce_wholesale_l10n', array( __CLASS__, 'add_l10n_items' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'add_inventory_button' ) );

		add_action( 'wp_ajax_zwoowh_reduce_all_stock_levels', array( __CLASS__, 'ajax_reduce_all_stock_levels' ) );
		add_action( 'wp_ajax_zwoowh_restore_all_stock_levels', array( __CLASS__, 'ajax_restore_all_stock_levels' ) );
	}

	public static function disable_payment_complete_stock_reduction_for_wholesale( $allowed, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order && $order->get_meta( Wholesale_Order::get_wholesale_custom_field() ) ) {
			$allowed = false;
		}

		return $allowed;
	}

	public static function add_l10n_items( $l10n ) {
		$l10n['l10n']['confirmReduceStock'] = esc_attr__( 'This will reduce the stock levels for all the products in the order by the quantities selected. Are you sure you want to proceed?', 'zwoowh' );
		$l10n['l10n']['confirmRestoreStock'] = esc_attr__( 'This will restore the stock levels for all the products in the order by the quantities originally deducted. Are you sure you want to proceed?', 'zwoowh' );

		return $l10n;
	}

	public static function add_inventory_button( $order ) {
		$has_been_reduced = $order->get_meta( 'reduced_products_stock' );
		// echo '<xmp>'. __LINE__ .') $has_been_reduced: '. print_r( $has_been_reduced, true ) .'</xmp>';

		$class = ! empty( $has_been_reduced )
			? 'restore-all-stock-levels-button'
			: 'reduce-all-stock-levels-button';

		$text = ! empty( $has_been_reduced )
			? __( 'Restore all product stock levels', 'zwoowh' )
			: __( 'Reduce all product stock levels', 'zwoowh' );

		?>
		<span class="all-stock-levels-wrap">
			<span class="spinner"></span>
			<button type="button" class="button button-secondary button-link-delete <?php echo $class; ?>"><?php echo $text; ?></button>
		</span>
		<?php
	}

	public static function ajax_reduce_all_stock_levels() {
		self::handle_ajax_order_request_action( array( __CLASS__, 'reduce_and_track_stock_levels' ) );
	}

	public static function ajax_restore_all_stock_levels() {
		self::handle_ajax_order_request_action( array( __CLASS__, 'restore_tracked_stock_levels' ) );
	}

	protected static function handle_ajax_order_request_action( $callback ) {
		if ( empty( $_GET['order_id'] ) || ! ( $order = wc_get_order( absint( $_GET['order_id'] ) ) ) ) {
			wp_send_json_error();
		}

		if ( call_user_func( $callback, $order ) ) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	/**
	 * Reduce stock levels for items within an order and track which products changed.
	 * @since  0.1.0
	 * @param  int|WC_Order $order_id
	 * @return bool|array False if nothing was changed, or or array with product id => quantity changed.
	 */
	public static function reduce_and_track_stock_levels( $order_id ) {
		$order = $order_id instanceof \WC_Order
			? $order_id
			: wc_get_order( $order_id );

		add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'get_quantities' ), 10, 3 );
		add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'store_changed_quantities' ) );

		wc_reduce_stock_levels( $order );

		$stock_reduced = ! empty( self::$quantities ) ? self::$quantities : true;

		// Reset.
		self::$quantities = array();

		// Successful if any quantities were changed.
		return $stock_reduced;
	}

	public static function get_quantities( $quantity, $order, $item ) {
		self::$quantities[ $item->get_product()->get_id() ] = $quantity;
		return $quantity;
	}

	public static function store_changed_quantities( $order ) {
		if ( ! empty( self::$quantities ) ) {
			$order->update_meta_data( 'reduced_products_stock', self::$quantities );
			$order->save_meta_data();
		}
	}

	/**
	 * Restore stock levels for items within an order.
	 * @since  0.1.0
	 * @param  int|WC_Order $order_id
	 * @return bool True if quantities were restored.
	 */
	public static function restore_tracked_stock_levels( $order_id ) {
		$order = $order_id instanceof \WC_Order
			? $order_id
			: wc_get_order( $order_id );

		$products_stock = $order->get_meta( 'reduced_products_stock' );

		if ( empty( $products_stock ) || ! is_array( $products_stock ) ) {
			return false;
		}

		foreach ( $products_stock as $product_id => $qty ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$qty = apply_filters( 'zwoowh_reduce_order_product_quantity', $qty, $product, $order );
			$new_stock = wc_update_product_stock( $product, $qty, 'increase' );

			if ( is_wp_error( $new_stock ) ) {
				// whoops
				continue;
			}

			/* translators: 1: item name 2: old stock quantity 3: new stock quantity */
			$order->add_order_note( sprintf(
				__( '%1$s stock restored from %2$s to %3$s.', 'zwoowh' ),
				$product->get_formatted_name(),
				$new_stock - $qty,
				$new_stock
			) );
		}

		$order->delete_meta_data( 'reduced_products_stock' );
		$order->save_meta_data();

		// Ensure stock is no longer marked as "reduced".
		$order->get_data_store()->set_stock_reduced( $order->get_id(), false );

		do_action( 'zwoowh_restore_order_stock', $order );

		return true;
	}

}
