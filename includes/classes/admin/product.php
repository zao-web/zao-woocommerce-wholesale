<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Product extends Base {

	public function __construct() {}

	public function init() {

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_wholesale_margin_input' ) );
		add_action( 'woocommerce_process_product_meta'                , array( $this, 'save_wholesale_margin' ) );
		add_action( 'woocommerce_update_product'                      , array( $this, 'maybe_modify_visibility' ) );
		add_action( 'woocommerce_new_product'                         , array( $this, 'maybe_modify_visibility' ) );
	}


	/**
	 * Adds wholesale margin input to products.
	 *
	 * @return [type] [description]
	 */
	public function add_wholesale_margin_input() {
		global $product_object;

		woocommerce_wp_text_input( array(
			'id'          => '_zwoowh_wholesale_margin',
			'value'       => $product_object->get_meta( 'wholesale_margin', true, 'edit' ),
			'label'       => __( 'Wholesale margin', 'zwoowh' ),
			'description' => '<br />Add your wholesale margin. For example, if you have a $100 product, and sell it wholesale for $50, this value should be "2".',
		) );
	}

	public function save_wholesale_margin( $post_id ) {
		$product = wc_get_product( $post_id );

		$product->update_meta_data( 'wholesale_margin', floatval( $_POST['_zwoowh_wholesale_margin'] ) );
		$product->save_meta_data();
	}

	public function maybe_modify_visibility( $product_id ) {
		$wholesale_terms = get_the_terms( $product_id, Taxonomy::SLUG );
		if ( empty( $wholesale_terms ) || is_wp_error( $wholesale_terms ) ) {
			// If no wholesale terms, nothing to do here.
			return;
		}

		$product = wc_get_product( $product_id );
		$visibility = $product->get_catalog_visibility();

		if ( 'hidden' === $visibility ) {
			// If product's visibility is already set to hidden, nothing to do here.
			return;
		}

		$wholesale_terms = wp_list_pluck( $wholesale_terms, 'slug' );

		// If product is set to 'wholesale-only', then we need to make the catalog visibility "hidden".
		if ( in_array( 'wholesale-only', $wholesale_terms ) ) {
			$product->set_catalog_visibility( 'hidden' );
			$product->save();
		}
	}

}
