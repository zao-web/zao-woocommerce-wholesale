<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Wholesale_Order extends Admin {
	protected $is_wholesale = false;

	public function __construct() {
		global $pagenow;

		$this->is_wholesale = (
			'post-new.php' === $pagenow
			&& isset( $_GET['post_type'], $_GET['wholesale'] )
			&& 'shop_order' === $_GET['post_type']
		) || (
			isset( $_REQUEST['is_wholesale'] )
			&& wp_verify_nonce( $_REQUEST['is_wholesale'], __FILE__ )
		);
	}

	public function init() {
		if ( ! $this->is_wholesale ) {
			return;
		}

		$order_type_object = get_post_type_object( sanitize_text_field( 'shop_order' ) );
		$order_type_object->labels->add_new_item = __( 'Add new wholesale order', 'zwoowh' );

		add_filter( 'admin_body_class', array( $this, 'filter_admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_help' ) );
		add_action( 'admin_footer', array( $this, 'add_app' ) );

		add_action( 'wp_ajax_woocommerce_json_search_customers', array( $this, 'maybe_limit_user_search_to_wholesalers' ), 5 );
	}

	public function filter_admin_body_class( $body_class = '' ) {
		$body_class = trim( $body_class ) . ' is-wholesale-order init-wholesale-order';
		return $body_class;
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/css/zao-woocommerce-wholesale{$min}.css", array(), ZWOOWH_VERSION );
		wp_enqueue_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "/assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
		wp_localize_script( 'zao-woocommerce-wholesale', 'ZWOOWH', apply_filters( 'zao_woocommerce_wholesale_l10n', array(
			'is_wholesale' => wp_create_nonce( __FILE__ ),
		) ) );
	}

	public function add_help() {
		?>
		<script>
			var select = document.getElementById( 'customer_user' );
			select.setAttribute( 'data-placeholder', '<?php echo esc_js( __( 'Search for Wholesaler', 'zwoowh' ) ); ?>' );
			select.style.width = '99%';
		</script>
		<?php
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

	public function maybe_limit_user_search_to_wholesalers() {
		add_action( 'pre_get_users', array( $this, 'limit_user_search_to_wholesalers' ) );
	}

	public function limit_user_search_to_wholesalers( $query ) {
		$query->set( 'role', 'wc_wholesaler' );
	}

}
