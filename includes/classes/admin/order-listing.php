<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;

class Order_Listing extends Base {
	protected $is_wholesale = false;

	public function __construct() {}

	public function init() {
		add_action( 'admin_print_styles', array( $this, 'style_wholesale_tag' ), 999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'maybe_add_wholesale_tag' ), -5 );
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

		$wholesale_tag = '<small class="wholesale-tag">' . __( 'Wholesale', 'zwoowh' ) . '</small>';

		echo str_replace( '</strong></a>', '</strong></a>' . $wholesale_tag, ob_get_clean() );
	}

}
