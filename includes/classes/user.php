<?php

namespace Zao\ZaoWooCommerce_Wholesale;

class User {
	const ROLE = 'wc_wholesaler';

	public function init() {
		add_action( 'wp_loaded', array( $this, 'set_up_role' ) );
		add_action( 'user_register', array( __CLASS__, 'set_wholesale_users' ) );
		add_action( 'profile_update', array( __CLASS__, 'set_wholesale_users' ) );
		add_action( 'deleted_user', array( __CLASS__, 'set_wholesale_users' ) );
	}

	public function set_up_role() {
		if ( get_option( 'zwwho_roles_set_up' ) ) {
			return;
		}

		$roles = add_role(
			self::ROLE,
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

	public static function get_wholesale_users() {
		$users = get_transient( 'zwoowh_wholesale_users' );
		if ( empty( $users ) ) {
			$users = self::set_wholesale_users();
		}

		return $users;
	}

	public static function set_wholesale_users() {
		$users = get_users( array(
			'role' => self::ROLE,
			'fields' => array( 'ID', 'display_name', 'user_email' ),
		) );

		set_transient( 'zwoowh_wholesale_users', $users, WEEK_IN_SECONDS );

		return $users;
	}
}
