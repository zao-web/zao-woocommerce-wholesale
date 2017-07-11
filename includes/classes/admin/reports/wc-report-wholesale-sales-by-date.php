<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

/**
 * WC_Report_Sales_By_Date
 *
 * @author      WooThemes
 * @category    Admin
 * @package     WooCommerce/Admin/Reports
 * @version     2.1.0
 */
class WC_Report_Wholesale_Sales_By_Date extends \WC_Report_Sales_By_Date {}
