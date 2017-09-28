<?php

namespace Zao\ZaoWooCommerce_Wholesale;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Order_Cloner extends Accessible {
	protected $orig_order;
	protected $order;
	protected $data = null;
	protected $meta = null;
	protected $billing = null;
	protected $shipping = null;

	public function __construct( $orig_order ) {
		$orig_order = $orig_order instanceof \WC_Order
			? $orig_order
			: wc_get_order( $orig_order );

		if ( ! $orig_order ) {
			throw new \Exception( 'Order Cloner requires a proper order object or ID.' );
		}

		$this->orig_order = $orig_order;
	}

	public function clone() {
		try {

			$this->order = new \WC_Order();

			$this->order->set_props( $this->get_orig_data() );

			$this
				->clone_order_billing()
				->clone_order_shipping()
				->clone_order_meta();

			$this->order->update_meta_data( 'original_order', $this->orig_order->get_id() );

			do_action( 'zwoowh_order_cloner_pre_save', $this );

			$this->order->save_meta_data();
			$this->order->save();

		} catch ( \Exception $e ) {
			$this->order = new \WP_Error( 'order_cloner_error', $e->getMessage() );
		}

		return $this->order;
	}

	public function get_orig_data() {
		if ( null === $this->data ) {
			$no_transfer_props = array(
				'id'             => 1,
				'discount_total' => 1,
				'discount_tax'   => 1,
				'shipping_total' => 1,
				'shipping_tax'   => 1,
				'cart_tax'       => 1,
				'total'          => 1,
				'total_tax'      => 1,
				'order_key'      => 1,
				'customer_note'  => 1,
				'cart_hash'      => 1,
				'line_items'     => 1,
				'tax_lines'      => 1,
				'shipping_lines' => 1,
				'fee_lines'      => 1,
				'coupon_lines'   => 1,
				'meta_data'      => 1,
				'number'         => 1,
				'billing'        => 1,
				'shipping'       => 1,
			);

			$this->data = $this->orig_order->get_data();

			foreach ( $this->data as $key => $value ) {
				if ( isset( $no_transfer_props[ $key ] ) ) {
					unset( $this->data[ $key ] );
				}
			}
		}

		return $this->data;
	}

	public function get_orig_meta() {
		if ( null === $this->meta ) {
			$this->meta = $this->orig_order->get_meta_data();

			foreach ( $this->meta as $index => $meta ) {
				unset( $this->meta[ $index ]->id );
			}
		}

		return $this->meta;
	}

	public function clone_order_meta() {
		foreach ( $this->get_orig_meta() as $meta ) {
			$this->order->update_meta_data( $meta->key, $meta->value );
		}

		return $this;
	}

	public function get_orig_billing() {
		if ( null === $this->billing ) {
			$this->billing = array(
				'first_name' => $this->orig_order->get_billing_first_name(),
				'last_name'  => $this->orig_order->get_billing_last_name(),
				'company'    => $this->orig_order->get_billing_company(),
				'address_1'  => $this->orig_order->get_billing_address_1(),
				'address_2'  => $this->orig_order->get_billing_address_2(),
				'city'       => $this->orig_order->get_billing_city(),
				'state'      => $this->orig_order->get_billing_state(),
				'postcode'   => $this->orig_order->get_billing_postcode(),
				'country'    => $this->orig_order->get_billing_country(),
				'email'      => $this->orig_order->get_billing_email(),
				'phone'      => $this->orig_order->get_billing_phone(),
			);
		}

		return $this->billing;
	}

	public function get_orig_shipping() {
		if ( null === $this->shipping ) {
			$this->shipping = array(
				'first_name' => $this->orig_order->get_shipping_first_name(),
				'last_name'  => $this->orig_order->get_shipping_last_name(),
				'company'    => $this->orig_order->get_shipping_company(),
				'address_1'  => $this->orig_order->get_shipping_address_1(),
				'address_2'  => $this->orig_order->get_shipping_address_2(),
				'city'       => $this->orig_order->get_shipping_city(),
				'state'      => $this->orig_order->get_shipping_state(),
				'postcode'   => $this->orig_order->get_shipping_postcode(),
				'country'    => $this->orig_order->get_shipping_country(),
			);
		}

		return $this->shipping;
	}

	protected function clone_order_billing() {
		foreach ( $this->get_orig_billing() as $key => $value ) {
			$method = 'set_billing_' . $key;
			$this->order->{$method}( $value );
		}

		return $this;
	}

	protected function clone_order_shipping() {
		foreach ( $this->get_orig_shipping() as $key => $value ) {
			$method = 'set_shipping_' . $key;
			$this->order->{$method}( $value );
		}

		return $this;
	}

}
