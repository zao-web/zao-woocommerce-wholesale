<?php

namespace Zao\ZaoWooCommerce_Wholesale;

class User {

	public function init() {
		add_action( 'wp_loaded', array( $this, 'set_up_role' ) );
	}

	public function set_up_role() {
		if ( get_option( 'zwwho_roles_set_up' ) ) {
			return;
		}

		$roles = add_role(
			'wc_wholesaler',
			__( 'Wholesaler' ),
			array(
				'read'            => true,
				'order_wholesale' => true,
			)
		);

		if ( $roles ) {
			update_option( 'zwwho_roles_set_up', true );
		}
	}

}
