<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base;
use Zao\ZaoWooCommerce_Wholesale\Order_Cloner;
use Zao\ZaoWooCommerce_Wholesale\Order_Item_Cloner;

/**
 * Handling backorders for wholesale orders.
 */
class Backorders_Management extends Inventory_Management {
	protected static $backorders_list = array();
	public function __construct() {}

	public function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'maybe_register_backorders_metabox' ) );
		add_action( 'woocommerce_order_item_add_action_buttons', array( __CLASS__, 'add_split_order_button' ) );
		add_action( 'wp_ajax_zwoowh_split_order_to_backorders', array( __CLASS__, 'ajax_split_order_to_backorders' ) );
	}

	public static function maybe_register_backorders_metabox( $post_type ) {
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		$order = isset( $_GET['post'] ) ? wc_get_order( absint( $_GET['post'] ) ) : false;
		if ( ! $order ) {
			return;
		}

		self::generate_backorders_list();

		if ( empty( self::$backorders_list ) ) {
			return;
		}

		$title = $order->get_meta( 'original_order' )
			? __( 'Associated Wholesale Order', 'zwoowh' )
			: __( 'Connected Backorders', 'zwoowh' );

		add_meta_box(
			'zwoowh-backorders',
			$title,
			array( __CLASS__, 'output_connected_orders' ),
			$post_type,
			'side'
		);
	}

	protected static function generate_backorders_list( $order ) {
		$original_order = absint( $order->get_meta( 'original_order' ) );
		$backorders     = $order->get_meta( 'split_backorders' );

		if ( $original_order ) {
			self::$backorders_list[] = array(
				'url'   => get_edit_post_link( $original_order ),
				'title' => sprintf( '#%d', $original_order ),
			);
		} elseif ( ! empty( $backorders ) ) {
			$count = count( $backorders );

			foreach ( $backorders as $product_id => $order_id ) {
				$back_order = wc_get_order( absint( $order_id ) );

				// Backorder was likely deleted.
				if ( ! $back_order ) {
					unset( $backorders[ $product_id ] );
					continue;
				}

				self::$backorders_list[] = array(
					'url'   => get_edit_post_link( $back_order->get_id() ),
					'title' => sprintf( '#%d', $back_order->get_id() ),
				);
			}

			if ( $count > count( $backorders ) ) {

				if ( ! empty( $backorders ) ) {
					$order->update_meta_data( 'split_backorders', $backorders );
				} else {
					$order->delete_meta_data( 'split_backorders' );
				}

				$order->save_meta_data();
			}
		}
	}

	public static function output_connected_orders( $post ) {
		if ( ! empty( self::$backorders_list ) ) {
			echo '<ol class="zwoowh-backorders-list">';
			foreach ( self::$backorders_list as $item ) {
				printf( '<li><a href="%s">%s</a></li>', $item['url'], $item['title'] );
			}
			echo '</ol>';
		}
	}

	public static function add_split_order_button( $order ) {
		// Do not show split button on orders which are already a backorder.
		if ( $order->get_meta( 'original_order' ) ) {
			return;
		}

		try {
			$backorders = self::get_proposed_backorders( $order, false );
		} catch ( \Exception $e ) {
			$backorders = false;
		}


		if ( ! empty( $backorders ) ) {
			?>
			<span class="zwoowh-action-button-wrap">
				<button type="button" class="button button-secondary button-link-delete split-into-backorders-button" data-confirmation="<?php esc_attr_e( 'This will split any order items that are backordered into separate orders. Are you sure you want to proceed?', 'zwoowh' ); ?>" data-action="zwoowh_split_order_to_backorders"><?php _e( 'Split out backorders', 'zwoowh' ); ?></button>
			</span>
			<?php
		}
	}

	public static function ajax_split_order_to_backorders() {
		parent::handle_ajax_order_request_action( array( __CLASS__, 'split_order_to_backorders' ) );
	}

	public static function split_order_to_backorders( $order_id ) {
		$created = false;

		try {
			$order = parent::get_order( $order_id );
			$backorders = self::get_proposed_backorders( $order, true );

			if ( ! empty( $backorders ) ) {
				$created = self::create_backorders( $backorders, $order );
			}

		} catch ( \Exception $e ) {}

		return $created;
	}

	public static function get_proposed_backorders( $order_id, $update = false ) {
		$order = parent::get_order( $order_id );

		$all_products = array();
		foreach ( $order->get_items() as $item ) {
			$product = $order->get_product_from_item( $item );
			if ( ! $product || ! $product->get_manage_stock() ) {
				continue;
			}

			if ( isset( $all_products[ $product->get_id() ] ) ) {

				$all_products[ $product->get_id() ]['items'][] = $item;

			} else {

				$all_products[ $product->get_id() ] = array(
					'product' => $product,
					'items' => array( $item ),
				);
			}
		}

		$backorders = array();

		foreach ( $all_products as $id => &$product ) {
			$items = $product['items'];

			$available_qty = $product['product']->get_stock_quantity();

			foreach ( $items as $item ) {

				if ( $item['quantity'] > $available_qty ) {

					$deficit = is_numeric( $available_qty ) ? $item['quantity'] - $available_qty : 0;

					if ( $deficit ) {

						$backorders[ $product['product']->get_id() ][ $item->get_id() ][] = array(
							// 'item' => $item,
							'item' => $item,
							'qty'  => $deficit,
						);

						// error_log( __FUNCTION__ . ':' . __LINE__ .') $item: '. print_r( array(
						// 	'item' => $item->get_name() . ' ('. $item->get_id() .')',
						// 	'prod_id' => $id,
						// 	'item attmempted qty' => $item['quantity'],
						// 	'backorder item qty' => $deficit,
						// 	'item new qty' => $available_qty,
						// 	'remove item from order?' => $available_qty <= 0,
						// ), true ) );

						if ( $update ) {
							if ( $available_qty > 0 ) {
								$_product = Order_Item_Cloner::get_item_product( $item );

								if ( $_product ) {
									$item->set_product( $_product );
									$item = Order_Item_Cloner::set_item_totals( $item, $available_qty );
									$item->save();
								}

							} else {
								$order->remove_item( $item->get_id() );
							}
						}
					}
				}

				$available_qty -= $item['quantity'];
			}
		}

		if ( $update ) {
			$order->calculate_totals();
		}

		return $backorders;
	}

	public static function create_backorders( $split_orders, $order_id ) {
		$orig_order = parent::get_order( $order_id );

		$backorders = array();

		foreach ( $split_orders as $product_id => $to_order ) {
			$cloner = new Order_Cloner( $orig_order );
			$order = $cloner->clone();
			if ( is_wp_error( $order ) ) {
				return $order;
			}

			$order_id = $order->get_id();
			$backorders[ $product_id ] = $order_id;

			foreach ( $to_order as $item_id => $lines ) {
				foreach ( $lines as $lines_key => $orig_item ) {
					$item_cloner = new Order_Item_Cloner( $order_id, $orig_item['item'] );
					$item = $item_cloner->clone( $orig_item['qty'] );

					if ( is_wp_error( $item ) ) {
						$order->delete( true );

						return $item;
					}

					if ( ! $item ) {
						continue;
					}

					$item->set_quantity( $orig_item['qty'] );

					$order->add_item( $item );
				}
			}

			$order->calculate_totals();
		}

		$orig_order->update_meta_data( 'split_backorders', $backorders );
		$orig_order->save_meta_data();

		return $backorders;
	}

}
