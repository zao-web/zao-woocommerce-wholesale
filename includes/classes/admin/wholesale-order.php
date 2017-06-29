<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

class Wholesale_Order extends Admin {
	public function __construct() {}

	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function enqueue() {
		// wp_die( '<xmp>'. __FUNCTION__ . ':' . __LINE__ .') '. print_r( get_defined_vars(), true ) .'</xmp>' );

		// wp_enqueue_script( 'vue-2.3.4', ZWOOWH_URL . "/assets/js/vendor/vue-2.3.4/vue-2.3.4{$min}.js", $deps, $ver, $in_footer );
		wp_enqueue_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/js/zao-woocommerce-wholesale{$min}.js", array( 'vue-2.3.4' ), ZWOOWH_VERSION, true );

	}
}
