<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * Handling quantities for the wholesale management interface.
 */
class Inventory_Management extends Order_Base {
	protected static $quantities = array();
	public function __construct() {}

	public function init() {

		// For wholesale orders, do not reduce stock levels for items
		// within an order when a payment is complete.
		// We will require clicking the button to do so.
		add_filter( 'woocommerce_payment_complete_reduce_order_stock', array( __CLASS__, 'disable_payment_complete_stock_reduction_for_wholesale' ), 10, 2 );
		add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'add_inventory_button' ) );

		add_action( 'wp_ajax_zwoowh_reduce_all_stock_levels', array( __CLASS__, 'ajax_reduce_all_stock_levels' ) );
		add_action( 'wp_ajax_zwoowh_restore_all_stock_levels', array( __CLASS__, 'ajax_restore_all_stock_levels' ) );
	}

	public static function disable_payment_complete_stock_reduction_for_wholesale( $allowed, $order_id ) {
		if ( parent::is_wholesale( $order_id ) ) {
			$allowed = false;
		}

		return $allowed;
	}

	public static function add_inventory_button( $order ) {
		$has_been_reduced = $order->get_meta( 'reduced_products_stock' );
		// echo '<xmp>'. __LINE__ .') $has_been_reduced: '. print_r( $has_been_reduced, true ) .'</xmp>';

		if ( ! empty( $has_been_reduced ) ) {
			$class   = 'restore-all-stock-levels-button';
			$text    = __( 'Restore all product stock levels', 'zwoowh' );
			$confirm = __( 'This will reduce the stock levels for all the products in the order by the quantities selected. Are you sure you want to proceed?', 'zwoowh' );
			$action  = 'zwoowh_reduce_all_stock_levels';
		} else {
			$class   = 'reduce-all-stock-levels-button';
			$text    = __( 'Reduce all product stock levels', 'zwoowh' );
			$confirm = __( 'This will restore the stock levels for all the products in the order by the quantities originally deducted. Are you sure you want to proceed?', 'zwoowh' );
			$action  = 'zwoowh_restore_all_stock_levels';
		}

		?>
		<span class="zwoowh-action-button-wrap">
			<button type="button" class="button button-secondary button-link-delete <?php echo $class; ?>" data-confirmation="<?php echo esc_attr( $confirm ); ?>" data-action="<?php echo $action; ?>"><?php echo $text; ?></button>
		</span>
		<?php
	}

	public static function ajax_reduce_all_stock_levels() {
		parent::handle_ajax_order_request_action( array( __CLASS__, 'reduce_and_track_stock_levels' ) );
	}

	public static function ajax_restore_all_stock_levels() {
		parent::handle_ajax_order_request_action( array( __CLASS__, 'restore_tracked_stock_levels' ) );
	}

	/**
	 * Reduce stock levels for items within an order and track which products changed.
	 * @since  0.1.0
	 * @param  int|WC_Order $order_id
	 * @return bool|array False if nothing was changed, or or array with product id => quantity changed.
	 */
	public static function reduce_and_track_stock_levels( $order_id ) {
		try {
			$order = parent::get_order( $order_id );

			add_filter( 'woocommerce_order_item_quantity', array( __CLASS__, 'get_quantities' ), 10, 3 );
			add_action( 'woocommerce_reduce_order_stock', array( __CLASS__, 'store_changed_quantities' ) );

			wc_reduce_stock_levels( $order );

			$stock_reduced = ! empty( self::$quantities ) ? self::$quantities : true;

			// Reset.
			self::$quantities = array();

		} catch ( \Exception $e ) {
			$stock_reduced = false;
		}

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
		try {
			$order = parent::get_order( $order_id );

			$products_stock = $order->get_meta( 'reduced_products_stock' );

			if ( empty( $products_stock ) || ! is_array( $products_stock ) ) {
				return false;
			}

			foreach ( $products_stock as $product_id => $qty ) {
				$product = wc_get_product( $product_id );

				if ( ! $product || ! $product->exists() || ! $product->managing_stock() ) {
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

		} catch ( \Exception $e ) {
			return false;
		}
	}

}
