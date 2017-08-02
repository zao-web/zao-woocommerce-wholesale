<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\User;

/**
 * The order admin interface for wholesale orders.
 *
 * @todo Limit the customer select2 to only wc_wholesaler users.
 */
class Wholesale_Order extends Admin {
	protected $is_wholesale = false;
	protected $products = array();

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

		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_wholesale_order_meta' ) );
		add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'filter_wholesale_pricing' ), 10, 3 );

		add_filter( 'woocommerce_dynamic_pricing_process_product_discounts', '__return_false' );

		if ( ! $this->is_wholesale ) {
			return;
		}

		$order_type_object = get_post_type_object( sanitize_text_field( 'shop_order' ) );
		$order_type_object->labels->add_new_item = __( 'Add new wholesale order', 'zwoowh' );

		add_filter( 'admin_body_class'     , array( $this, 'filter_admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer'         , array( $this, 'add_app' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_help' ) );

		add_action( 'wp_ajax_woocommerce_json_search_customers', array( $this, 'maybe_limit_user_search_to_wholesalers' ), 5 );

		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'set_item_qty' ), 10, 2 );

	}

	public function filter_admin_body_class( $body_class = '' ) {
		$body_class = trim( $body_class ) . ' is-wholesale-order init-wholesale-order';
		return $body_class;
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/css/zao-woocommerce-wholesale{$min}.css", array(), ZWOOWH_VERSION );
		wp_register_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
		add_action( 'admin_footer', array( $this, 'localize_data' ), 12 );
	}

	public function localize_data() {
		wp_enqueue_script( 'zao-woocommerce-wholesale' );
		$data = apply_filters( 'zao_woocommerce_wholesale_l10n', array(
			'rest_url'          => rest_url(),
			'placeholderImgSrc' => wc_placeholder_img_src(),
			'rest_nonce'        => wp_create_nonce( 'wp_rest' ),
			'is_wholesale'      => wp_create_nonce( __FILE__ ),
			'select_id'         => self::should_replace_dropdown() ? 'wholesale_user' : 'customer_user',
			'allProducts'       => array(),
			'variableProducts'  => array(),
			'columns'           => array(
				array(
					'name' => 'img',
					'title' => __( 'Thumb', 'zwoowh' ),
					'filter' => false,
				),
				array(
					'name' => 'sku',
					'title' => __( 'SKU', 'zwoowh' ),
				),
				array(
					'name' => 'name',
					'title' => __( 'Name/Variation', 'zwoowh' ),
				),
				array(
					'name' => 'price',
					'title' => __( 'Price', 'zwoowh' ),
				),
				array(
					'name' => 'qty',
					'title' => __( 'Quantity', 'zwoowh' ),
					'filter' => false,
				),
				array(
					'name' => 'categories',
					'title' => __( 'Categories', 'zwoowh' ),
				),
			),
			'searchParams' => array(
				'name',
				'parent',
				'sku',
				'categories',
			),
			'productFields' => array(
				'id',
				'img:40',
				'sku',
				'name',
				'price',
				'variations',
				'type',
				'manage_stock',
				'stock_quantity',
				'in_stock',
				'editlink',
				'category_names',
			),
			'allCategories' => get_terms( 'product_cat', array( 'fields' => 'names', 'update_term_meta_cache' => false ) ),
			'l10n' => array(
				'somethingWrong'       => __( 'Something went wrong and we were not able to retrieve the wholesale products.', 'zwoowh' ),
				'noStockTitle'         => __( 'This item is out of stock.', 'zwoowh' ),
				'addProductsBtn'       => __( 'Add Products', 'zwoowh' ),
				'clearBtn'             => __( 'Clear', 'zwoowh' ),
				'selectProductsTitle'  => __( 'Select Products', 'zwoowh' ),
				'variantProductsTitle' => __( 'Variant Products', 'zwoowh' ),
				'customTaxName'        => '',
				'categoryTitle'        => __( 'Categories', 'zwoowh' ),
				'searchPlaceholder'    => __( 'Filter products by id, sku, name, parent, price, etc', 'zwoowh' ),
				'plsWait'              => __( 'Loading Products. Please try again in a second.', 'zwoowh' ),
				'closeBtn'             => __( 'Close product selector', 'zwoowh' ),
				'insertBtn'            => __( 'Insert', 'zwoowh' ),
			),
		) );

		// wp_die( '<xmp style="padding-left:300px">'. __LINE__ .') $data: '. print_r( $data, true ) .'</xmp>' );
		wp_localize_script( 'zao-woocommerce-wholesale', 'ZWOOWH', $data );
	}

	public static function should_replace_dropdown() {
		static $replace_dropdown = null;
		if ( null === $replace_dropdown ) {
			$users = User::get_wholesale_users();

			// If we have less than 500 wholesale users, let's create a snappier dropdown that doesn't require ajax searches.
			$replace_dropdown =  empty( $users ) || count( $users ) < 500;

			// But allow overriding via filter.
			$replace_dropdown = apply_filters( 'zao_woocommerce_wholesale_replace_search_dropdown', $replace_dropdown );
		}

		return $replace_dropdown;
	}

	public function add_help( $order ) {
		$users = User::get_wholesale_users();

		// If we have less than 500 wholesale users, let's create a snappier dropdown that doesn't require ajax searches.
		if ( self::should_replace_dropdown() ) {

			$user_id = '';
			if ( $order->get_user_id() ) {
				$user_id = absint( $order->get_user_id() );
			}

			?>
			<script>
				var select = document.getElementById( 'customer_user' );
				select.parentElement.removeChild( select );
			</script>
			<select class="wc-wholesale-search" id="wholesale_user" name="wholesale_user" data-placeholder="<?php esc_attr_e( 'Search for Wholesaler', 'zwoowh' ); ?>" data-allow_clear="true" style="width:99%;">
				<option value="" <?php selected( ! $user_id ); ?>><?php esc_attr_e( 'Select Wholesaler', 'zwoowh' ); ?></option>
				<?php foreach ( $users as $user ) {
					/* translators: 1: user display name 2: user ID 3: user email */
					$user_string = sprintf(
						esc_html__( '%1$s (#%2$s - %3$s)', 'zwoowh' ),
						$user->display_name,
						absint( $user->ID ),
						$user->user_email
					); ?>

				<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id === absint( $user->ID ) ); ?>><?php echo htmlspecialchars( $user_string ); ?></option>

				<?php } ?>
			</select>
			<?php

		} else { ?>
			<script>
				var select = document.getElementById( 'customer_user' );
				select.setAttribute( 'data-placeholder', '<?php echo esc_js( __( 'Search for Wholesaler', 'zwoowh' ) ); ?>' );
				select.style.width = '99%';
			</script>
		<?php }
		?>
		<input type="hidden" name="is_wholesale" value="1" />
		<?php
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

	public function maybe_limit_user_search_to_wholesalers() {
		add_action( 'pre_get_users', array( $this, 'limit_user_search_to_wholesalers' ) );
	}

	/**
	 * Filters product price in admin when adding items to the cart.
	 *
	 * @param  [type] $price    [description]
	 * @param  [type] $quantity [description]
	 * @param  [type] $product  [description]
	 * @return [type]           [description]
	 */
	public function filter_wholesale_pricing( $price, $quantity, $product ) {

		if ( ! is_admin() || ! current_user_can( 'publish_shop_orders' ) ) {
			return $price;
		}

		if ( ! doing_action( 'wp_ajax_woocommerce_add_order_item' ) ) {
			return $price;
		}

		// Margins are currently set on parent product, not per-variation
		if ( 'variation' === $product->get_type() ) {
			$_product = wc_get_product( $product->get_parent_id() );
			$margin   = $_product->get_meta( 'wholesale_margin' );
		} else {
			$margin = $product->get_meta( 'wholesale_margin' );
		}

		if ( $margin ) {
			return round( $price / $margin, 2 );
		}

		return $price;

	}

	public function save_wholesale_order_meta( $post_id ) {

		parse_str( wp_get_referer(), $output );

		if ( isset( $output['wholesale'] ) && 'true' === $output['wholesale'] ) {

			$product = wc_get_order( $post_id );

			$product->update_meta_data( 'is_wholesale_order', true );
			$product->save_meta_data();

		}

	}

	public function limit_user_search_to_wholesalers( $query ) {
		$query->set( 'role', 'wc_wholesaler' );

		$users = User::get_wholesale_users();

		// Because a huge $wpdb->users.ID IN ($ids) query is probably not better for performance,
		// We'll only set the 'include' parameter for less than 300 wholesale users.
		if ( ! empty( $users ) && count( $users ) < 300 ) {
			$query->set( 'include', wp_list_pluck( $users, 'ID' ) );
		}
	}

	/**
	 * Use the hacked together order item array keys to determine the line item quantities.
	 *
	 * @since 0.1.0
	 *
	 * @param WC_Order_Item_Product  $order_item_product
	 * @param object  $data_store
	 */
	public function set_item_qty( $order_item_product, $data_store ) {
		if ( empty( $_REQUEST['item_to_add'] ) ) {
			return;
		}

		$product_id = $order_item_product->get_variation_id();

		if ( ! $product_id ) {
			$product_id = absint( $order_item_product->get_product_id( 'edit' ) );
		}

		foreach ( $_REQUEST['item_to_add'] as $key => $prod_id_to_check ) {

			if (
				absint( $prod_id_to_check ) !== $product_id
				|| 0 !== strpos( $key, $product_id . ':' )
			) {
				continue;
			}

			$parts = explode( ':', $key );
			if ( empty( $parts[1] ) ) {
				continue;
			}

			$quantity = absint( $parts[1] );

			$order_item_product->set_quantity( $quantity );
			$order_item_product->set_total( $order_item_product->get_total() * $quantity );

			$this->add_product_quantity( $product_id, $quantity );

			break;
		}
	}

	public function add_product_quantity( $product_id, $quantity ) {
		static $hooked = false;

		if ( isset( $this->products[ $product_id ] ) ) {
			$this->products[ $product_id ] += $quantity;
		} else {
			$this->products[ $product_id ] = $quantity;
		}

		if ( ! $hooked ) {
			// A hack to send the data to the browser "just in time"
			add_filter( 'wp_die_ajax_handler', array( $this, 'send_products_header' ) );
			$hooked = true;
		}
	}

	public function send_products_header( $function ) {

		// Hackily sending this data back to the browser.
		@header( 'X-ZWOOWH-products: ' . json_encode( $this->products ) );

		return $function;
	}
}
