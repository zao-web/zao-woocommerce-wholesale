<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Order_Listing extends Base {
	protected $is_wholesale = false;

	public function __construct() {}

	public function init() {
		add_action( 'admin_print_styles', array( $this, 'style_wholesale_tag' ), 999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'maybe_add_wholesale_tag' ), -5 );
		if ( isset( $_GET['wholesale_only'] ) ) {
			add_action( 'pre_get_posts', array( $this, 'maybe_filter_to_wholesale_only' ) );
		}
	}

	public function style_wholesale_tag() {
		$screen = get_current_screen();
		if ( $screen && 'shop_order' === $screen->post_type && 'edit' === $screen->base ) {
			?>
			<style type="text/css">
				.wholesale-tag {
					text-transform: uppercase;
					margin-left: 5px;
					padding: 3px 4px;
					letter-spacing: 1px;
					background: #d9dbf1;
					font-size: .8em;
					font-weight: bold;
					color: #000;
				}
			</style>
			<?php
		}
	}

	public function maybe_add_wholesale_tag( $column ) {
		if ( 'order_title' !== $column ) {
			return;
		}

		global $post, $the_order;

		if ( empty( $the_order ) || $the_order->get_id() !== $post->ID ) {
			$the_order = wc_get_order( $post->ID );
		}

		$this->is_wholesale = $the_order->get_meta( Wholesale_Order::get_wholesale_custom_field() );

		if ( $this->is_wholesale ) {
			ob_start();
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_wholesale_tag' ) );
		}
	}

	public function add_wholesale_tag( $column ) {
		if ( 'order_title' !== $column || ! $this->is_wholesale ) {
			return;
		}

		$filter_url = add_query_arg( 'wholesale_only', 1 );
		$wholesale_tag = '<a class="wholesale-tag" href="' . esc_url( $filter_url )  . '"><small>' . __( 'Wholesale', 'zwoowh' ) . '</small></a>';

		echo str_replace( '</strong></a>', '</strong></a>' . $wholesale_tag, ob_get_clean() );
	}

	public function maybe_filter_to_wholesale_only( $query ) {
		if ( ! $query->is_main_query() || 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );

		if ( empty( $meta_query ) ) {
			$meta_query = array();
		}

		$meta_query[] = array(
			'key' => Wholesale_Order::get_wholesale_custom_field(),
			'compare' => 'EXISTS',
		);

		$query->set( 'meta_query', $meta_query );
	}

}
