<?php

namespace Zao\ZaoWooCommerce_Wholesale;

class Taxonomy {

	const SLUG = 'wholesale-category';
	public $tax_mb;

	public function init() {

		$labels = array(
			'name'                  => _x( 'Wholesale Categories', 'Taxonomy plural name', 'zwoowh' ),
			'singular_name'         => _x( 'Wholesale Category', 'Taxonomy singular name', 'zwoowh' ),
			'search_items'          => __( 'Search Wholesale Categories', 'zwoowh' ),
			'popular_items'         => __( 'Popular Wholesale Categories', 'zwoowh' ),
			'all_items'             => __( 'All Wholesale Categories', 'zwoowh' ),
			'parent_item'           => __( 'Parent Wholesale Category', 'zwoowh' ),
			'parent_item_colon'     => __( 'Parent Wholesale Category', 'zwoowh' ),
			'edit_item'             => __( 'Edit Wholesale Category', 'zwoowh' ),
			'update_item'           => __( 'Update Wholesale Category', 'zwoowh' ),
			'add_new_item'          => __( 'Add New Wholesale Category', 'zwoowh' ),
			'new_item_name'         => __( 'New Wholesale Category Name', 'zwoowh' ),
			'add_or_remove_items'   => __( 'Add or remove Wholesale Categories', 'zwoowh' ),
			'choose_from_most_used' => __( 'Choose from most used zwoowh', 'zwoowh' ),
			'menu_name'             => __( 'Wholesale Category', 'zwoowh' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_in_nav_menus'  => false,
			'publicly_queryable' => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'show_tagcloud'      => false,
			'show_ui'            => false,
		);

		register_taxonomy( self::SLUG, array( 'product' ), $args );

		if ( is_admin() ) {
			$this->set_tax_mb();
		}
	}

	public function set_tax_mb() {
		require_once ZWOOWH_INC . 'third-party/Taxonomy_Single_Term/class.taxonomy-single-term.php';

		// https://github.com/WebDevStudios/Taxonomy_Single_Term/
		$this->tax_mb = new \Taxonomy_Single_Term( self::SLUG );

		// Priority of the metabox placement.
		$this->tax_mb->set( 'priority', 'low' );

		// 'normal' to move it under the post content.
		$this->tax_mb->set( 'context', 'side' );

		// Custom title for your metabox
		$this->tax_mb->set( 'metabox_title', __( 'Available for Wholesale?', 'yourtheme' ) );

		add_action( 'admin_footer', array( $this, 'maybe_add_helper_text' ) );

		if ( is_admin() ) {
			if ( ! term_exists( 'wholesale-only', self::SLUG ) ) {
				// initiate the terms.
				wp_insert_term( 'Wholesale Only', self::SLUG, array(
					'slug' => 'wholesale-only',
				) );
				wp_insert_term( 'Wholesale + Retail', self::SLUG, array(
					'slug' => 'wholesale',
				) );
			}
		}
	}

	public function maybe_add_helper_text() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if (
			! isset( $screen->post_type )
			|| 'product' !== $screen->post_type
		) {
			return;
		}

		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( '#wholesale-category_input_element .inside' ).prepend( '<p class="description"><?php esc_html_e( 'By default products are retail-only. Select below to enable wholesale for this product.', 'zwoowh' ); ?></p>' );
			} );
		</script>
		<?php
	}

	public static function set_wholesale_term( $product_id, $slug = 'wholesale-only' ) {
		if ( false === $slug ) {
			return wp_delete_object_term_relationships( $product_id, self::SLUG );
		}

		return wp_set_object_terms( $product_id, 'wholesale-only' === $slug ? 'wholesale-only' : 'wholesale', self::SLUG );
	}

	public static function get_wholesale_term( $product_id ) {
		$wholesale_terms = get_the_terms( $product_id, self::SLUG );

		if ( empty( $wholesale_terms[0] ) || is_wp_error( $wholesale_terms ) ) {
			// If no wholesale terms, nothing to do here.
			return false;
		}

		return $wholesale_terms[0]->slug;
	}

	public static function is_wholesale_product( $product_id ) {
		$slug = self::get_wholesale_term( $product_id );
		return ! empty( $slug );
	}

	public static function is_wholesale_only( $product_id ) {
		return 'wholesale-only' === self::get_wholesale_term( $product_id );
	}

	public static function is_wholesale_and_retail( $product_id ) {
		return 'wholesale' === self::get_wholesale_term( $product_id );
	}

}
