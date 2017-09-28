<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\Base, WC_Order, Exception;

abstract class Order_Base extends Base {
	public static $success_send = null;

	public static function get_order( $order_id ) {
		$order = $order_id instanceof WC_Order
			? $order_id
			: wc_get_order( $order_id );

		if ( ! $order ) {
			throw new Exception( 'Order not found.' );
		}

		return $order;
	}

	public static function get_order_being_edited() {
		global $pagenow;
		if ( 'post.php' !== $pagenow || ! isset( $_GET['post'] ) ) {
			return false;
		}

		$post_id   = absint( $_GET['post'] );
		$post_type = get_post_type( $post_id );

		if ( 'shop_order' !== $post_type ) {
			return false;
		}

		return wc_get_order( $post_id );
	}

	public static function is_wholesale( $order_id ) {
		try {
			$order = self::get_order( $order_id );

			return $order && $order->get_meta( Wholesale_Order::get_wholesale_custom_field() );

		} catch ( Exception $e ) {
			return false;
		}
	}

	protected static function output_notice( $message, $query_var ) {
		$query_val = isset( $_GET[ $query_var ] ) ? $_GET[ $query_var ] : '';
		?>
		<div id="message" class="updated notice is-dismissible">
			<?php echo wpautop( $message ); ?>
		</div>

		<script type="text/javascript">
			if ( window.history.replaceState ) {
				window.history.replaceState( null, null, window.location.href.replace( /\?<?php echo $query_var; ?>\=<?php echo $query_val; ?>\&/, '?' ).replace( /(\&|\?)<?php echo $query_var; ?>\=<?php echo $query_val; ?>/, '' ) );
			}
		</script>
		<?php
	}

	protected static function modify_order_label( $label_key, $value ) {
		$order_type_object = get_post_type_object( 'shop_order' );
		$order_type_object->labels->{$label_key} = $value;
	}

	protected static function handle_ajax_order_request_action( $callback ) {
		if ( empty( $_GET['order_id'] ) || ! ( $order = wc_get_order( absint( $_GET['order_id'] ) ) ) ) {
			wp_send_json_error();
		}

		$result = call_user_func( $callback, $order );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		if ( $result ) {
			wp_send_json_success( self::$success_send );
		}

		wp_send_json_error();
	}
}
