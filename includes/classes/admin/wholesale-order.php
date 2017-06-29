<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

class Wholesale_Order extends Admin {
	public function __construct() {}

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer', array( $this, 'add_app' ) );
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

}
