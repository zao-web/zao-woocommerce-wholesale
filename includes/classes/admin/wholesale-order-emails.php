<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base, WC_Order;

/**
 * Adding ShipStation interface (if key/secret is defined)
 */
class Wholesale_Order_Emails extends Order_Base {
	public function __construct() {}

	public function init() {
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'disable_if_order_is_wholesale' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'disable_if_order_is_wholesale' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'disable_if_order_is_wholesale' ), 10, 2 );
	}

	public function disable_if_order_is_wholesale( $enabled, $order ) {
		if ( is_a( $order, 'WC_Order' ) && parent::is_wholesale( $order ) ) {
			$enabled = false;
		}

		return $enabled;
	}
}
