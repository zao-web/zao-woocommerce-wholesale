<?php

namespace Zao\ZaoWooCommerce_Wholesale;

class REST_API {

	protected static $request;

	public function init() {
		add_action( 'rest_request_before_callbacks', array( $this, 'store_request' ), 10, 3 );
		add_action( 'pre_get_posts', array( $this, 'maybe_filter_wholesale' ) );
		add_action( 'rest_request_after_callbacks', array( $this, 'maybe_modify_response' ), 10, 3 );
	}

	public function store_request( $response, $handler, $request ) {
		self::$request = $request;
		return $response;
	}

	public function maybe_filter_wholesale( $query ) {
		if ( ! empty( self::$request['wholesale'] ) ) {
			$tax_query = $query->get( 'tax_query' );

			if ( ! is_array( $tax_query ) ) {
				$tax_query = array();
			}

			$tax_query[] = array(
				'taxonomy' => Admin\Taxonomy::SLUG,
				'field'    => 'slug',
				'terms'    => array( 'wholesale-only', 'wholesale' ),
			);

			$query->set( 'tax_query', $tax_query );
		}
	}

	public function maybe_modify_response( $response, $handler, $request ) {

		if ( ! isset( $request['zwoowh_limit_fields'] ) ) {
			return $response;
		}

		$filters = array_map( 'trim', explode( ',', $request['zwoowh_limit_fields'] ) );

		if ( empty( $filters ) ) {
			return $response;
		}

		$is_variation_route = preg_match( '~\/wc\/v2\/products\/[0-9]+\/variations~', $request->get_route() );

		if ( ! empty( $response->data ) && ! empty( $filters ) && ! is_wp_error( $response ) ) {
			$taxonomies = array_flip( get_object_taxonomies( 'product' ) );

			foreach ( $response->data as $key => $product ) {

				$main_product_id = isset( $request['product_id'] ) ? absint( $request['product_id'] ) : $product['id'];

				$limited_product = array();
				foreach ( $filters as $filter ) {

					if ( isset( $product[ $filter ] ) ) {

						$limited_product[ $filter ] = $product[ $filter ];

					} elseif ( 'category_names' === $filter ) {

						$limited_product['categories'] = ! empty( $product['categories'] ) ? wp_list_pluck( $product['categories'], 'name' ) : array();

					} elseif ( isset( $taxonomies[ $filter ] ) ) {

						$tax = get_taxonomy( $filter );

						$response->header( 'X-ZWOOWH-customTaxName', $tax->label, true );

						$terms = get_the_terms( absint( $main_product_id ), $filter );
						$limited_product['custom_tax'] = ! is_wp_error( $terms ) && isset( $terms[0]->name ) ? $terms[0]->name : '';

					} elseif ( 'editlink' === $filter ) {

						$limited_product['editlink'] = get_edit_post_link( $main_product_id, 'raw' );

					} elseif ( 'wholesale_price' === $filter ) {

						// Margins are currently set on parent product, not per-variation
						$_product = wc_get_product( $main_product_id );
						$margin   = $_product->get_meta( 'wholesale_margin' );
						$wholesale_price = $product['price'];

						if ( $margin ) {
							$wholesale_price = round( $wholesale_price / $margin, 2 );
						}

						$limited_product['wholesale_price'] = $wholesale_price;

					} elseif ( $is_variation_route && 'name' === $filter && ! empty( $product['attributes'] ) ) {

						$limited_product['name'] = self::attributes_name( $product );

					} elseif ( 0 === strpos( $filter, 'img' ) ) {

						$img = self::get_product_image( $product['id'], $filter );

						if ( $img ) {
							$limited_product['img'] = $img;
						}

					}
				}

				$response->data[ $key ] = empty( $limited_product ) ? $product['id'] : $limited_product;
			}

		}

		return $response;
	}

	public static function attributes_name( $product ) {
		$name = array();
		foreach ( $product['attributes'] as $attribute ) {
			$name[] = ucfirst( $attribute['name'] ) . ' — ' . $attribute['option'];
		}

		return implode( ',', $name );
	}

	public static function get_product_image( $product_id, $filter ) {
		$parts = explode( ':', $filter );
		$size = 'full';
		if ( isset( $parts[1] ) ) {
			if ( is_numeric( $parts[1] ) ) {
				$size = array( $parts[1], $parts[1] );
			} else {
				$size = $parts[1];
			}
		}

		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $product_id ), $size );
		if ( ! empty( $img[0] ) ) {
			return $img;
		}

		return false;
	}

}
