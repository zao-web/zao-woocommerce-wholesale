<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base, Zao\ZaoWooCommerce_Wholesale\Taxonomy;

/**
 * Modifications to Woo Products.
 */
class Product extends Base {

	public function __construct() {}

	public function init() {

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_wholesale_margin_input' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_virtual_weight_input' ), 20 );
		add_action( 'woocommerce_process_product_meta'                , array( $this, 'save_custom_meta' ) );
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

	/**
	 * Adds wholesale margin input to products.
	 *
	 * @return [type] [description]
	 */
	public function add_virtual_weight_input() {
		global $product_object;

		woocommerce_wp_text_input( array(
			'id'          => '_zwoowh_virtual_weight',
			'value'       => $product_object->get_meta( 'virtual_product_weight', true, 'edit' ),
			'label'       => __( 'Shipping Weight for Virtual Product', 'zwoowh' ),
			'description' => '<br />Enter a weight here for virtual products that require a weight when mailed internationally.',
		) );
	}

	public function save_custom_meta( $product_id ) {
		$product = wc_get_product( $product_id );

		$product->update_meta_data( 'wholesale_margin', floatval( $_POST['_zwoowh_wholesale_margin'] ) );
		$product->update_meta_data( 'virtual_product_weight', floatval( $_POST['_zwoowh_virtual_weight'] ) );
		$product->save_meta_data();
	}

	public function maybe_modify_visibility( $product_id ) {
		if ( ! Taxonomy::is_wholesale_only( $product_id ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		$visibility = $product->get_catalog_visibility();

		if ( 'hidden' === $visibility ) {
			// If product's visibility is already set to hidden, nothing to do here.
			return;
		}

		// If product is set to 'wholesale-only', then we need to make the catalog visibility "hidden".
		$product->set_catalog_visibility( 'hidden' );
		$product->save();
	}

}
