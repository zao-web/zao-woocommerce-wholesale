<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

/**
 * The order admin interface for wholesale orders.
 */
class Report extends Admin {

	public function __construct() {}

	public function init() {
		add_filter( 'woocommerce_admin_reports', array( $this, 'add_wholesale_report' ) );
		add_filter( 'wc_admin_reports_path'    , array( $this, 'add_wholesale_report_path' ), 10, 3 );
		add_filter( 'woocommerce_reports_get_order_report_query', function( $query ) {
			 error_log( var_export( $query['where'], 1 ) );
			 $query['where'] = str_replace( "meta_is_wholesale_order.meta_value IS NULL 'zwoowh_null_test'", 'meta_is_wholesale_order.post_id IS NULL', $query['where'] );

 			 error_log( var_export( $query['where'], 1 ) );
			 return $query;
		 } );
		add_filter( 'woocommerce_reports_get_order_report_data_args', function( $args ) {

			$is_wholesale = isset( $_GET['report'] ) && 'wholesale_sales_by_date' === $_GET['report'];

			if ( $is_wholesale ) {
				$args['where_meta'] = array(
					array(
						'meta_key' => 'is_wholesale_order',
						'meta_value' => 1,
						'type' => 'wholesale',
						'operator' => '='
					)
				);
			} else {
				$args['where_meta'] = array(
					array(
						'meta_key' => 'is_wholesale_order',
						'meta_value' => 'zwoowh_null_test',
						'type' => 'wholesale',
						'operator'  => 'IS NULL',
						'join_type' => 'LEFT'
					)
				);
			}



			return $args;
		} );
	}

	public function add_wholesale_report( $reports ) {
		$reports['orders']['reports']['wholesale_sales_by_date'] = array(
			'title'       => __( 'Wholesale sales by date', 'zwoowh' ),
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
