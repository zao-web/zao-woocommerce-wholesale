<?php

namespace Zao\ZaoWooCommerce_Wholesale;

class Admin extends Base {
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ), 999 );
	}

	public function register_menu_page() {
		$wholesale_page = add_submenu_page(
			'woocommerce',
			__( 'Add Wholesale Order', 'zwoowh' ),
			__( 'Wholesale Order', 'zwoowh' ),
			'manage_woocommerce',
			'wholesale',
			'__return_empty_string'
		);

		add_action( 'load-' . $wholesale_page, array( $this, 'redirect_to_new_wholesale_order' ) );
	}

	public function redirect_to_new_wholesale_order() {
		$new_order_url = admin_url( 'post-new.php?post_type=shop_order&wholesale=true' );
		wp_safe_redirect( $new_order_url );
		exit;
	}
}
