<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * Handling quantities for the wholesale management interface.
 */
class Inventory_Management extends Base {
	protected $quantities = array();
	public function __construct() {}

	public function init() {
		add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'add_inventory_button' ) );
		add_action( 'wp_ajax_zwoowh_reduce_all_stock_levels', array( $this, 'ajax_reduce_all_stock_levels' ) );
		add_action( 'wp_ajax_zwoowh_restore_all_stock_levels', array( $this, 'ajax_restore_all_stock_levels' ) );
		add_filter( 'zao_woocommerce_wholesale_l10n', array( $this, 'add_l10n_items' ) );

		// For wholesale orders, do not reduce stock levels for items
		// within an order when a payment is complete.
		// This requires clicking the button to do so.
		add_filter( 'woocommerce_payment_complete_reduce_order_stock', array( $this, 'disable_payment_complete_stock_reduction_for_wholesale' ), 10, 2 );
	}

	public function disable_payment_complete_stock_reduction_for_wholesale( $allowed, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order && $order->get_meta( Wholesale_Order::get_wholesale_custom_field() ) ) {
			$allowed = false;
		}

		return $allowed;
	}

	public function add_inventory_button( $order ) {
		$has_been_reduced = $order->get_meta( 'reduced_products_stock' );

		$class = ! empty( $has_been_reduced )
			? 'restore-all-stock-levels-button'
			: 'reduce-all-stock-levels-button';

		$text = ! empty( $has_been_reduced )
			? __( 'Restore all product stock levels', 'woocommerce' )
			: __( 'Reduce all product stock levels', 'woocommerce' );

		?>
		<span class="all-stock-levels-wrap">
			<span class="spinner"></span>
			<button type="button" class="button button-secondary button-link-delete <?php echo $class; ?>"><?php echo $text; ?></button>
		</span>
		<?php
	}

	public function ajax_reduce_all_stock_levels() {
		if ( empty( $_GET['order_id'] ) || ! ( $order = wc_get_order( absint( $_GET['order_id'] ) ) ) ) {
			wp_send_json_error();
		}

		add_filter( 'woocommerce_order_item_quantity', array( $this, 'get_quantities' ), 10, 3 );

		wc_reduce_stock_levels( $order );

		$order->update_meta_data( 'reduced_products_stock', $this->quantities );
		$order->save_meta_data();

		wp_send_json_success();
	}

	public function get_quantities( $quantity, $order, $item ) {
		$this->quantities[ $item->get_product()->get_id() ] = $quantity;
		return $quantity;
	}

	public function ajax_restore_all_stock_levels() {
		if ( empty( $_GET['order_id'] ) || ! ( $order = wc_get_order( absint( $_GET['order_id'] ) ) ) ) {
			wp_send_json_error();
		}

		$products_stock = $order->get_meta( 'reduced_products_stock' );

		if ( ! empty( $products_stock ) && is_array( $products_stock ) ) {
			foreach ( $products_stock as $product_id => $qty ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					$new_stock = wc_update_product_stock( $product, $qty, 'increase' );
				}
			}
		}

		$order->delete_meta_data( 'reduced_products_stock' );
		$order->save_meta_data();
		$order->get_data_store()->set_stock_reduced( $order->get_id(), false );

		wp_send_json_success();
	}

	public function add_l10n_items( $l10n ) {
		$l10n['l10n']['confirmReduceStock'] = esc_attr__( 'This will reduce the stock levels for all the products in the order by the quantities selected. Are you sure you want to proceed?', 'zwqoi' );
		$l10n['l10n']['confirmRestoreStock'] = esc_attr__( 'This will restore the stock levels for all the products in the order by the quantities originally deducted. Are you sure you want to proceed?', 'zwqoi' );

		return $l10n;
	}

	/**
	 * Reduce stock levels for items within an order.
	 * @since 3.0.0
	 * @param int|WC_Order $order_id
	 */
	public static function restore_stock_levels( $order_id ) {
		if ( is_a( $order_id, 'WC_Order' ) ) {
			$order    = $order_id;
			$order_id = $order->get_id();
		} else {
			$order = wc_get_order( $order_id );
		}
		if ( 'yes' === get_option( 'woocommerce_manage_stock' ) && $order && apply_filters( 'woocommerce_can_restore_order_stock', true, $order ) && sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item->is_type( 'line_item' ) && ( $product = $item->get_product() ) && $product->managing_stock() ) {
					$qty       = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
					$item_name = $product->get_formatted_name();
					$new_stock = wc_update_product_stock( $product, $qty, 'increase' );

					if ( ! is_wp_error( $new_stock ) ) {
						/* translators: 1: item name 2: old stock quantity 3: new stock quantity */
						$order->add_order_note( sprintf( __( '%1$s stock restored from %2$s to %3$s.', 'woocommerce' ), $item_name, $new_stock + $qty, $new_stock ) );

						// Get the latest product data.
						$product = wc_get_product( $product->get_id() );

						if ( '' !== get_option( 'woocommerce_notify_no_stock_amount' ) && $new_stock <= get_option( 'woocommerce_notify_no_stock_amount' ) ) {
							do_action( 'woocommerce_no_stock', $product );
						} elseif ( '' !== get_option( 'woocommerce_notify_low_stock_amount' ) && $new_stock <= get_option( 'woocommerce_notify_low_stock_amount' ) ) {
							do_action( 'woocommerce_low_stock', $product );
						}

						if ( $new_stock < 0 ) {
							do_action( 'woocommerce_product_on_backorder', array( 'product' => $product, 'order_id' => $order_id, 'quantity' => $qty ) );
						}
					}
				}
			}

			// ensure stock is no longer marked as "reduced".
			$order->get_data_store()->set_stock_reduced( $order_id, false );

			do_action( 'woocommerce_restore_order_stock', $order );
		}
	}
}
