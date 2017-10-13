<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base, WC_Order;

/**
 * Adding ShipStation interface (if key/secret is defined)
 */
class Wholesale_Order_Emails extends Order_Base {
	protected $emails_to_disable = array(
		'new_order',
		'customer_processing_order',
		'customer_completed_order',
	);

	public function __construct() {}

	public function init() {
		foreach ( $this->emails_to_disable as $action ) {
			add_filter( "woocommerce_email_enabled_{$action}", array( $this, 'disable_if_order_is_wholesale' ), 10, 2 );
		}

		add_action( 'woocommerce_before_resend_order_emails', array( $this, 'maybe_enable_for_resend' ) );
	}

	public function disable_if_order_is_wholesale( $enabled, $order ) {
		if ( is_a( $order, 'WC_Order' ) && parent::is_wholesale( $order ) ) {
			$enabled = false;
		}

		return $enabled;
	}

	public function maybe_enable_for_resend( $order ) {
		if ( empty( $_POST['wc_order_action'] ) ) {
			return;
		}

		$action = str_replace( 'send_email_', '', wc_clean( $_POST['wc_order_action'] ) );

		if ( in_array( $action, $this->emails_to_disable ) ) {
			remove_filter( "woocommerce_email_enabled_{$action}", array( $this, 'disable_if_order_is_wholesale' ), 10, 2 );
		}
	}

}
