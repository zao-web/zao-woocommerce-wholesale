<?php
namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Admin extends Base {
	protected $menu;
	protected $wholesale_order;
	protected $product;
	protected $taxonomy;

	public function __construct() {
		$this->menu            = new Menu;
		$this->wholesale_order = new Wholesale_Order;
		$this->product         = new Product;
		$this->report          = new Report;
		$this->taxonomy        = new Taxonomy;
	}

	public function init() {
		$this->menu->init();
		$this->wholesale_order->init();
		$this->product->init();
		$this->report->init();
		$this->taxonomy->init();
	}
}
