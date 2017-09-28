<?php

namespace Zao\ZaoWooCommerce_Wholesale;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Order_Item_Cloner extends Accessible {
	protected $orig_item;
	protected $item;
	protected $order_id;

	public function __construct( $order_id, $orig_item ) {
		$orig_item = $orig_item instanceof \WC_Order_Item
			? $orig_item
			: \WC_Order_Factory::get_order_item( $orig_item );

		if ( ! $orig_item ) {
			throw new \Exception( 'Order Item Cloner requires a proper order item object or ID.' );
		}

		$this->orig_item = $orig_item;
		$this->order_id  = $order_id;
	}

	public function clone( $qty = 1 ) {
		try {

			$this->item = \WC_Order_Factory::get_order_item( $this->clone_orig_item() );

			if ( $this->item ) {

				$this
					->clone_item_props()
					->clone_item_product_prop( $qty );

				$this->item->set_meta_data( $this->orig_item->get_meta_data() );

				$this->item->save_meta_data();
				$this->item->save();
			}

		} catch ( \Exception $e ) {
			$this->item = new \WP_Error( 'order_item_cloner_error', $e->getMessage() );
		}

		return $this->item;
	}

	public function clone_orig_item() {
		return \WC_Data_Store::load( 'order-item' )->add_order_item(
			$this->order_id,
			wp_parse_args( array(
				'order_item_name' => $this->orig_item->get_name( 'edit' ),
				'order_item_type' => $this->orig_item->get_type(),
			), array(
				'order_item_name' => '',
				'order_item_type' => 'line_item',
			) )
		);
	}

	protected function clone_item_props() {
		$props = array(
			'tax_class',
		);

		foreach ( $props as $prop ) {
			$get_method = 'get_' . $prop;
			$set_method = 'set_' . $prop;
			if ( is_callable( array( $this->orig_item, $get_method ) ) && is_callable( array( $this->orig_item, $set_method ) ) ) {
				$this->item->{$set_method}( $this->orig_item->{$get_method}( 'edit' ) );
			}
		}

		return $this;
	}

	protected function clone_item_product_prop( $qty ) {
		$product = self::get_item_product( $this->orig_item );

		if ( $product ) {
			$this->item->set_product( $product );
			self::set_item_totals( $this->item, $qty );
		}

		return $this;
	}

	public static function set_item_totals( $item, $qty ) {
		$product = self::get_item_product( $item );

		if ( $product ) {
			$item->set_quantity( $qty );

			$total = wc_get_price_excluding_tax( $product, array( 'qty' => $qty ) );
			$item->set_total( $total );
			$item->set_subtotal( $total );
		}

		return $item;
	}

	public static function get_item_product( $item ) {
		$product = null;
		if ( is_callable( array( $item, 'get_product' ) ) ) {
			$product = $item->get_product();
		}

		return $product;
	}

}
