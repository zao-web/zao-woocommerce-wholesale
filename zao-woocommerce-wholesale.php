<?php
/**
 * Plugin Name: Zao WooCommerce Wholesale
 * Plugin URI:  https://zao.is
 * Description: Generate wholesale orders for WooCommerce
 * Version:     0.1.0.5
 * Author:      Zao
 * Author URI:  https://zao.is
 * Text Domain: zwoowh
 * Domain Path: /languages
 * License:     GPL-2.0+
 * WC tested up to: 3.2.0
 */

/**
 * Copyright (c) 2017 Zao (email : jt@zao.is)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using yo wp-make:plugin
 * Copyright (c) 2017 10up, LLC
 * https://github.com/10up/generator-wp-make
 */

// Useful global constants
define( 'ZWOOWH_VERSION', defined( 'WP_DEBUG' ) && WP_DEBUG ? time() : '0.1.0.5' );
define( 'ZWOOWH_URL',     plugin_dir_url( __FILE__ ) );
define( 'ZWOOWH_PATH',    dirname( __FILE__ ) . '/' );
define( 'ZWOOWH_INC',     ZWOOWH_PATH . 'includes/' );

// Include files
require_once ZWOOWH_INC . 'functions/core.php';

// Activation/Deactivation
register_activation_hook( __FILE__, '\Zao\ZaoWooCommerce_Wholesale\activate' );
register_deactivation_hook( __FILE__, '\Zao\ZaoWooCommerce_Wholesale\deactivate' );

// Bootstrap
Zao\ZaoWooCommerce_Wholesale\setup();
