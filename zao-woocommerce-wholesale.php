<?php
/**
 * Plugin Name: Zao WooCommerce Wholesale
 * Plugin URI:  https://zao.is
 * Description: Generate wholesale orders for WooCommerce
 * Version:     0.1.0
 * Author:      Zao
 * Author URI:  https://zao.is
 * Text Domain: zwoowh
 * Domain Path: /languages
 * License:     GPL-2.0+
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
define( 'ZWOOWH_VERSION', '0.1.0' );
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


// The following is to be moved to a custom BT addon plugin


add_filter( 'zao_woocommerce_wholesale_l10n', function( $l10n ) {

	// $url = '/wc/v2/products';
	// $request = new WP_REST_Request( 'GET', $url );
	// $request['_wpnonce'] = wp_create_nonce( 'wp_rest' );
	// $request['status'] = 'publish';
	// $request['per_page'] = 100;
	// $request['type'] = 'simple';
	// $request['bt_limit_fields'] = 'id,img:50,sku,name,price,bt_product_type,stock_quantity,editlink';

	// $response = rest_do_request( $request );

	// $l10n['allProducts'] = $response->data;

	return $l10n;
}, 10 );

add_filter( 'rest_request_after_callbacks', function( $response, $handler, $request ) {

	if ( isset( $request['ids_only'] ) ) {
		$response->data = wp_list_pluck( $response->data, 'id' );
	}

	if ( isset( $request['bt_limit_fields'] ) ) {
		$variation_route = preg_match( '~\/wc\/v2\/products\/[0-9]+\/variations~', $request->get_route() );
		$filters = array_map( 'trim', explode( ',', $request['bt_limit_fields'] ) );


		if ( ! empty( $response->data ) && ! empty( $filters ) && ! is_wp_error( $response ) ) {
			foreach ( $response->data as $key => $product ) {
				$main_product_id = isset( $request['product_id'] ) ? absint( $request['product_id'] ) : $product['id'];

				$limited_product = array();
				foreach ( $filters as $filter ) {

					if ( isset( $product[ $filter ] ) ) {

						$limited_product[ $filter ] = $product[ $filter ];

					} elseif ( 'bt_product_type' === $filter ) {

						$terms = get_the_terms( absint( $main_product_id ), $filter );
						$limited_product['type'] = isset( $terms[0]->name ) ? $terms[0]->name : '';

					} elseif ( 'editlink' === $filter ) {

						$limited_product['editlink'] = get_edit_post_link( $main_product_id, 'raw' );

					} elseif ( $variation_route && 'name' === $filter && ! empty( $product['attributes'] ) ) {

						$name = array();
						foreach ( $product['attributes'] as $attribute ) {
							$name[] = ucfirst( $attribute['name'] ) . ' — ' . $attribute['option'];
						}
						$limited_product['name'] = implode( ',', $name );

					} elseif ( 0 === strpos( $filter, 'img' ) ) {

						$parts = explode( ':', $filter );
						$size = 'full';
						if ( isset( $parts[1] ) ) {
							if ( is_numeric( $parts[1] ) ) {
								$size = array( $parts[1], $parts[1] );
							} else {
								$size = $parts[1];
							}
						}

						$img = wp_get_attachment_image_src( get_post_thumbnail_id( $product['id'] ), $size );
						if ( ! empty( $img[0] ) ) {
							$limited_product['img'] = esc_url_raw( $img[0] );
						}

					}
				}

				$response->data[ $key ] = empty( $limited_product ) ? $product['id'] : $limited_product;
			}

		}
		// if (  is_wp_error( $response ) ) {
		// 	error_log( __LINE__ .') $response: '. print_r( $response, true ) );
		// }

	}

	// error_log( __LINE__ .') $response: '. print_r( $response, true ) );
	return $response;
}, 10, 3 );
