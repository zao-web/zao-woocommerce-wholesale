<?php
namespace Zao\ZaoWooCommerce_Wholesale;

class Frontend extends Base {

	public function init() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect_product' ) );
	}

	public function maybe_redirect_product() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		if ( ! Taxonomy::is_wholesale_only( get_queried_object() ) ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( is_object( $product ) && 'hidden' === $product->get_catalog_visibility() ) {
			wp_safe_redirect( esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ) );
			exit;
		}

	}
}
