<?php
namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Admin extends Base {
	protected $menu;
	protected $wholesale_order;

	public function __construct() {
		$this->menu = new Menu;
		$this->wholesale_order = new Wholesale_Order();
	}

	public function init() {
		$this->menu->init();
		$this->wholesale_order->init();
	}
}
