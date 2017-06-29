<?php
namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Admin extends Base {
	protected $is_wholesale = false;

	protected $menu;
	protected $wholesale_order;

	public function __construct() {
		global $pagenow;

		$this->is_wholesale = (
			'post-new.php' === $pagenow
			&& isset( $_GET['post_type'], $_GET['wholesale'] )
			&& 'shop_order' === $_GET['post_type']
		);

		$this->menu = new Menu;

		if ( $this->is_wholesale ) {
			$this->wholesale_order = new Wholesale_Order;
		}
	}

	public function init() {
		$this->menu->init();

		if ( $this->is_wholesale ) {
			$this->wholesale_order->init();
		}
	}
}
