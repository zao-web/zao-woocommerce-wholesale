<?php
namespace Zao\ZaoWooCommerce_Wholesale;

class Plugin extends Base {

	protected static $single_instance = null;

	protected $admin;

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
		if ( is_admin() ) {
			$this->admin = new Admin\Admin;
		}
	}

	public function init() {
		if ( is_admin() ) {
			$this->admin->init();
		}
	}

}
Plugin::get_instance();
