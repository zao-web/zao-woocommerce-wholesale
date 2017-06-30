<?php
namespace Zao\ZaoWooCommerce_Wholesale;

class Plugin extends Base {

	protected static $single_instance = null;

	protected $admin;

	protected $wholesale_users;

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return Plugin A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	protected function __construct() {

		$this->wholesale_users = new User;

		if ( is_admin() ) {
			$this->admin = new Admin\Admin;
		}
	}

	public function init() {

		$this->wholesale_users->init();

		if ( is_admin() ) {
			$this->admin->init();
		}
	}
}

Plugin::get_instance();
