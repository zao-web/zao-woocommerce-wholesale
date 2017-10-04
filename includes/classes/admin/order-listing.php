<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Order_Listing extends Order_Base {
	protected $is_wholesale = false;

	public function __construct() {}

	public function init() {
		add_action( 'admin_print_styles', array( $this, 'style_wholesale_tag' ), 999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'maybe_add_wholesale_tag' ), -5 );
		if ( isset( $_GET['wholesale_only'] ) || isset( $_GET['backorders_only'] ) ) {
			add_action( 'pre_get_posts', array( $this, 'maybe_filter_to_wholesale_only' ) );
		}
	}

	public function style_wholesale_tag() {
		$screen = get_current_screen();
		if ( $screen && 'shop_order' === $screen->post_type && 'edit' === $screen->base ) {
			?>
			<style type="text/css">
				.wholesale-tag {
					display: inline-block;
					text-transform: uppercase;
					margin-left: 5px;
					padding: 0px 4px;
					letter-spacing: 1px;
					background: #d9dbf1;
					font-size: .8em;
					font-weight: bold;
					color: #000;
				}
				mark.wholesale-back::after {
					content: '\e033'; /* Use the on-hold icon. */
					color: #ffba00;
					font-family: WooCommerce;
					speak: none;
					font-weight: 400;
					font-variant: normal;
					text-transform: none;
					line-height: 1;
					-webkit-font-smoothing: antialiased;
					margin: 0;
					text-indent: 0;
					position: absolute;
					top: 0;
					left: 0;
					width: 100%;
					height: 100%;
					text-align: center;
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

		$this->is_wholesale = parent::is_wholesale( $the_order );

		if ( $this->is_wholesale ) {
			ob_start();
			add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_wholesale_tag' ) );
		}
	}

	public function add_wholesale_tag( $column ) {
		global $the_order;
		if ( 'order_title' !== $column || ! $this->is_wholesale ) {
			return;
		}

		$filter_url = add_query_arg( 'wholesale_only', 1 );
		$wholesale_tag = '<a class="wholesale-tag" href="' . esc_url( $filter_url )  . '"><small>' . __( 'Wholesale', 'zwoowh' ) . '</small></a>';

		if ( parent::is_backorder( $the_order ) ) {
			$filter_url = add_query_arg( 'backorders_only', 1 );
			$wholesale_tag .= '<a class="wholesale-tag is-backorder" href="' . esc_url( $filter_url )  . '"><small>' . __( 'Backorder', 'zwoowh' ) . '</small></a>';
		}

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
			'key' => isset( $_GET['backorders_only'] ) ? 'original_order' : Wholesale_Order::get_wholesale_custom_field(),
			'compare' => 'EXISTS',
		);

		$query->set( 'meta_query', $meta_query );
	}

}
