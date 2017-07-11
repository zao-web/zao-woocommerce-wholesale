<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Report extends Admin {

	public function __construct() {}

	public function init() {
		add_filter( 'woocommerce_admin_reports', array( $this, 'add_wholesale_report' ) );
		add_filter( 'wc_admin_reports_path'    , array( $this, 'add_wholesale_report_path' ), 10, 3 );
	}

	public function add_wholesale_report( $reports ) {
		$reports['orders']['reports']['wholesale_sales_by_date'] = array(
			'title'       => __( 'Wholesale sales by date', 'woocommerce' ),
			'description' => '',
			'hide_title'  => true,
			'callback'    => array( 'WC_Admin_Reports', 'get_report' ),
		);

		return $reports;
	}

	public function add_wholesale_report_path( $path, $name, $class ) {
		if ( 'wholesale-sales-by-date' === $name ) {
			return ZWOOWH_INC . 'classes/admin/reports/wc-report-' . $name . '.php';
		}

		return $path;
	}

}
