<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Wholesale_Order extends Admin {
	public function __construct() {}

	public function init() {
		$order_type_object = get_post_type_object( sanitize_text_field( $_GET['post_type'] ) );
		$order_type_object->labels->add_new_item = __( 'Add new wholesale order', 'zwoowh' );

		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_help' ) );
		add_action( 'admin_footer', array( $this, 'add_app' ) );
	}

	public function filter_admin_body_class( $body_class = '' ) {
		$body_class = trim( $body_class ) . ' is-wholesale-order fresh-wholesale-order';
		return $body_class;
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
		wp_enqueue_style( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/css/zao-woocommerce-wholesale{$min}.css", array(), ZWOOWH_VERSION );
	}

	public function add_help() {
		echo '<h3 class="wholesale-help-title">' . __( 'First, Select a customer for this wholesale order.', 'zwoowh' ) . '</h3>';
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

}
