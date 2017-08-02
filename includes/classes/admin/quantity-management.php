<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Quantity_Management extends Base {
	protected $products = array();

	public function __construct() {}

	public function init() {
		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'set_order_item_qty' ), 10, 2 );
		add_action( 'woocommerce_before_delete_order_item', array( $this, 'send_restored_quantity' ) );
		add_action( 'woocommerce_before_save_order_items', array( $this, 'check_and_send_qty_changes' ), 10, 2 );
	}

	/**
	 * Use the hacked together order item array keys to determine the line item quantities
	 * before saving the order item.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order_Item_Product  $order_item_product
	 * @param object  $data_store
	 */
	public function set_order_item_qty( $order_item_product, $data_store ) {
		if ( empty( $_REQUEST['item_to_add'] ) ) {
			return;
		}

		$product_id = self::get_order_item_product_id( $order_item_product );

		foreach ( $_REQUEST['item_to_add'] as $key => $prod_id_to_check ) {

			if (
				absint( $prod_id_to_check ) !== $product_id
				|| 0 !== strpos( $key, $product_id . ':' )
			) {
				continue;
			}

			$parts = explode( ':', $key );
			if ( empty( $parts[1] ) ) {
				continue;
			}

			$quantity = absint( $parts[1] );

			$order_item_product->set_quantity( $quantity );
			$order_item_product->set_total( $order_item_product->get_total() * $quantity );

			$this->add_product_quantity( $product_id, $quantity );

			break;
		}
	}

	/**
	 * After an order item is deleted, sends back the updated quantity for the JS model's stock.
	 *
	 * @since  0.1.0
	 *
	 * @param  int  $item_id
	 */
	public function send_restored_quantity( $item_id ) {
		$order_item_product = \WC_Order_Factory::get_order_item( $item_id );
		$product_id = self::get_order_item_product_id( $order_item_product );

		$this->add_product_quantity(
			$product_id,
			$order_item_product->get_quantity(),
			'negate_and_send_products_header'
		);
	}

	/**
	 * Called _before_ saving order items, stores the original quantities before modification.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $order_id
	 * @param array $order_items
	 */
	public function check_and_send_qty_changes( $order_id, $order_items ) {
		$items = self::verify_items_and_get_order( $order_items, $order_id );
		if ( empty( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$product_id = self::get_order_item_product_id( $item );

			$this->products[ $product_id ] = $item->get_quantity();
		}

		add_action( 'woocommerce_saved_order_items', array( $this, 'check_qty_changed_and_send' ), 10, 2 );
	}

	/**
	 * Called _after_ saving order items, compares to original quantities to calculate
	 * the quantity changes to send back for the JS model's stock.
	 *
	 * @since 0.1.0
	 *
	 * @param int   $order_id
	 * @param array $order_items
	 */
	public function check_qty_changed_and_send( $order_id, $order_items ) {
		$items = self::verify_items_and_get_order( $order_items, $order_id );
		if ( empty( $items ) ) {
			return;
		}

		$products = array();

		foreach ( $items as $item ) {

			$product_id = self::get_order_item_product_id( $item );
			$quantity   = $item->get_quantity();

			$products[ $product_id ] = $quantity;

			$this->products[ $product_id ] = isset( $this->products[ $product_id ] )
				? $quantity - $this->products[ $product_id ]
				: $quantity;

			if ( empty( $this->products[ $product_id ] ) ) {
				unset( $this->products[ $product_id ] );
			}

		}

		add_filter( 'wp_die_ajax_handler', array( $this, 'send_products_header' ) );
	}

	/**
	 * Callback from wp_die. We do not actually need the filter functionality, but
	 * called "just in time" so we can send product data via a header.
	 *
	 * @since  0.1.0
	 *
	 * @param  string  $function
	 *
	 * @return string
	 */
	public function send_products_header( $function ) {

		// Hackily sending this data back to the browser.
		@header( 'X-ZWOOWH-products: ' . json_encode( $this->products ) );

		return $function;
	}

	// UTILITIES

	public static function verify_items_and_get_order( $order_items, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || empty( $order_items['order_item_id'] ) ) {
			return false;
		}

		$items = array();
		foreach ( $order_items['order_item_id'] as $item_id ) {
			if ( $item = $order->get_item( absint( $item_id ) ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	public function negate_and_send_products_header( $function ) {
		foreach ( $this->products as &$qty ) {
			$qty = -$qty;
		}

		return $this->send_products_header( $function );
	}

	public function add_product_quantity( $product_id, $quantity, $method = 'send_products_header' ) {
		static $hooked = false;

		if ( isset( $this->products[ $product_id ] ) ) {
			$this->products[ $product_id ] += $quantity;
		} else {
			$this->products[ $product_id ] = $quantity;
		}

		if ( ! $hooked ) {
			// A hack to send the data to the browser "just in time"
			add_filter( 'wp_die_ajax_handler', array( $this, $method ) );
			$hooked = true;
		}
	}

	public static function get_order_item_product_id( $order_item_product ) {
		$product_id = $order_item_product->get_variation_id();

		if ( ! $product_id ) {
			$product_id = absint( $order_item_product->get_product_id( 'edit' ) );
		}

		return absint( $product_id );
	}


}
