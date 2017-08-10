<?php
namespace Zao\ZaoWooCommerce_Wholesale;

class Plugin extends Base {

	protected static $single_instance = null;

	protected $admin;
	protected $wholesale_users;
	protected $rest_api;
	protected $taxonomy;
	protected $frontend;

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
		$this->rest_api        = new REST_API;
		$this->taxonomy        = new Taxonomy;

		if ( is_admin() ) {
			$this->admin = new Admin\Admin;
		} else {
			$this->frontend = new Frontend;
		}
	}

	public function init() {

		$this->wholesale_users->init();
		$this->rest_api->init();
		$this->taxonomy->init();

		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			$this->admin->init();
		} else {
			$this->frontend->init();
		}
	}

	public static function static_hooks() {
		add_filter( 'woocommerce_dynamic_pricing_process_product_discounts', array( __NAMESPACE__ . '\\Admin\\Wholesale_Order', 'remove_dynamic_pricing_if_wholesale' ) );
	}

}

Plugin::get_instance();
