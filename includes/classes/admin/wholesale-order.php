<?php

namespace Zao\ZaoWooCommerce_Wholesale\Admin;
use Zao\ZaoWooCommerce_Wholesale\User, Zao\ZaoWooCommerce_Wholesale\Base;

/**
 * The order admin interface for wholesale orders.
 */
class Wholesale_Order extends Order_Base {
	protected static $wholesale_custom_field = 'is_wholesale_order';
	protected static $is_wholesale           = null;
	protected static $is_edit_mode           = null;
	protected $products                      = array();
	protected $quantity_management           = null;
	protected $shipstation                   = null;
	protected $inventory_management          = null;
	protected $backorders_management         = null;
	protected $emails_management             = null;

	public function __construct() {
		if ( self::is_wholesale_context() ) {
			$this->quantity_management = new Quantity_Management;
			$this->inventory_management = new Inventory_Management;
			$this->backorders_management = new Backorders_Management;
		}

		$this->shipstation = new ShipStation;
		$this->emails_management = new Wholesale_Order_Emails;
	}

	public function init() {
		$this->emails_management->init();
		$this->shipstation->init();

		if ( self::is_wholesale_context() ) {

			$this->quantity_management->init();
			$this->inventory_management->init();
			$this->backorders_management->init();

			parent::modify_order_label( 'add_new_item', __( 'Add new wholesale order', 'zwoowh' ) );

			if ( self::is_wholesale_edit_context() ) {
				parent::modify_order_label( 'edit_item', __( 'Edit wholesale order', 'zwoowh' ) );
				add_action( 'admin_footer', array( $this, 'add_wholesale_order_buttons' ) );
			}

			add_filter( 'woocommerce_get_price_excluding_tax', array( $this, 'filter_wholesale_pricing_when_adding_order_item' ), 10, 3 );
			add_filter( 'woocommerce_product_get_price', array( $this, 'maybe_filter_wholesale_pricing' ), 10, 2 );
			add_filter( 'woocommerce_product_variation_get_price', array( $this, 'maybe_filter_wholesale_pricing' ), 10, 2 );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_wholesale_order_meta' ) );
			add_filter( 'admin_body_class'     , array( $this, 'filter_admin_body_class' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'admin_footer'         , array( $this, 'add_app' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_help' ) );

			add_action( 'wp_ajax_woocommerce_json_search_customers', array( $this, 'maybe_limit_user_search_to_wholesalers' ), 5 );

			add_action( 'woocommerce_order_actions_end', array( $this, 'add_frontend_view_link' ) );

		} else {
			add_action( 'admin_head', array( $this, 'maybe_add_wholesale_order_buttons' ), 9999 );
			add_action( 'admin_init', array( $this, 'process_backorder_export' ) );
		}
	}

	public static function register_backorder_status() {
		$label_count = __( 'Backordered (Wholesale) <span class="count">(%s)</span>', 'zwoowh' );

		register_post_status( 'wc-wholesale-back', array(
			'label'                     => __( 'Backordered (Wholesale)', 'zwoowh' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( $label_count, $label_count ),
		) );

		add_action( 'wc_order_statuses', array( __CLASS__, 'register_backorder_status_with_wc' ) );
	}

	public static function register_backorder_status_with_wc( $order_statuses ) {
		$order_statuses['wc-wholesale-back'] = _x( 'Backordered (Wholesale)', 'Order status', 'zwoowh' );

		return $order_statuses;
	}

	public function filter_admin_body_class( $body_class = '' ) {
		$body_class = trim( $body_class ) . ' is-wholesale-order ' . ( self::is_wholesale_edit_context() ? 'edit-wholesale-order' : 'init-wholesale-order' );
		return $body_class;
	}

	public function enqueue() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_style( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/css/zao-woocommerce-wholesale{$min}.css", array(), ZWOOWH_VERSION );
		wp_register_script( 'zao-woocommerce-wholesale', ZWOOWH_URL . "assets/js/zao-woocommerce-wholesale{$min}.js", array(), ZWOOWH_VERSION, true );
		$this->shipstation->register_script();
		add_action( 'admin_footer', array( $this, 'enqueue_and_localize_data' ), 12 );
	}

	public function enqueue_and_localize_data() {
		$this->shipstation->enqueue_script();

		wp_enqueue_script( 'zao-woocommerce-wholesale' );
		$data = apply_filters( 'zao_woocommerce_wholesale_l10n', array(
			'rest_url'          => rest_url(),
			'placeholderImgSrc' => wc_placeholder_img_src(),
			'rest_nonce'        => wp_create_nonce( 'wp_rest' ),
			'is_wholesale'      => wp_create_nonce( __FILE__ ),
			'replaceDropdown'   => self::should_replace_dropdown(),
			'is_edit_mode'      => self::is_wholesale_edit_context(),
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
					'title' => __( 'Wholesale Price', 'zwoowh' ),
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
				'wholesale_price',
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
				'msgReceived'          => __( 'The error found:', 'zwoowh' ),
				'noStockTitle'         => __( 'out of stock', 'zwoowh' ),
				'addProductsBtn'       => __( 'Add Products', 'zwoowh' ),
				'clearBtn'             => __( 'Clear', 'zwoowh' ),
				'selectProductsTitle'  => __( 'Select Products', 'zwoowh' ),
				'variantProductsTitle' => __( 'Variant Products', 'zwoowh' ),
				'clearFilters'         => __( 'Clear Filters', 'zwoowh' ),
				'customTaxName'        => '',
				'categoryTitle'        => __( 'Categories', 'zwoowh' ),
				'searchPlaceholder'    => __( 'Filter products by id, sku, name, parent, price, etc', 'zwoowh' ),
				'plsWait'              => __( 'Loading Products. Please try again in a second.', 'zwoowh' ),
				'closeBtn'             => __( 'Close product selector', 'zwoowh' ),
				'insertBtn'            => __( 'Insert', 'zwoowh' ),
				'origPrice'            => __( 'Original Price: $%d', 'zwoowh' ),
				'selectShipping'       => __( 'Select a shipping rate', 'zwoowh' ),
			),
		) );

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
			<select class="wc-wholesale-search" id="customer_user" name="customer_user" data-placeholder="<?php esc_attr_e( 'Search for Wholesaler', 'zwoowh' ); ?>" data-allow_clear="true" style="width:99%;">
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
		<input type="hidden" name="is_wholesale" value="<?php echo wp_create_nonce( __FILE__ ); ?>" />
		<?php
	}

	public function add_app() {
		echo '<div id="zwoowh"></div>';
	}

	public function maybe_limit_user_search_to_wholesalers() {
		add_action( 'pre_get_users', array( $this, 'limit_user_search_to_wholesalers' ) );
	}

	public function add_frontend_view_link( $order_id ) {
		$order = wc_get_order( $order_id );
		$url = $order ? $order->get_checkout_order_received_url( true ) : false;
		if ( $url ) {
			echo '<ul class="order_actions submitbox"><li class="wide"><div class="alignright"><a href="' . $url . '">' . __( 'View Order', 'zwoowh' ) . '</a></div></li></ul>';
		}
	}

	/**
	 * Filters product price in admin when adding items to the cart.
	 *
	 * @param  [type] $price    [description]
	 * @param  [type] $quantity [description]
	 * @param  [type] $product  [description]
	 * @return [type]           [description]
	 */
	public function filter_wholesale_pricing_when_adding_order_item( $price, $quantity, $product ) {
		if ( ! is_admin() || ! current_user_can( 'publish_shop_orders' ) ) {
			return $price;
		}

		if ( ! doing_action( 'wp_ajax_woocommerce_add_order_item' ) ) {
			return $price;
		}

		return $this->modify_wholesale_price( $price, $product );
	}

	/**
	 * Filters product price in admin when adding items to the cart.
	 *
	 * @param  [type] $price    [description]
	 * @param  [type] $product  [description]
	 * @return [type]           [description]
	 */
	public function maybe_filter_wholesale_pricing( $price, $product ) {
		if ( ! is_admin() || ! current_user_can( 'publish_shop_orders' ) ) {
			return $price;
		}

		return $this->modify_wholesale_price( $price, $product );
	}

	/**
	 * Filters product price in admin when adding items to the cart.
	 *
	 * @param  [type] $price    [description]
	 * @param  [type] $product  [description]
	 * @return [type]           [description]
	 */
	public function modify_wholesale_price( $price, $product ) {
		static $product_prices = array();

		// Margins are currently set on parent product, not per-variation
		if ( 'variation' === $product->get_type() ) {
			$product = wc_get_product( $product->get_parent_id() );
		}

		$product_id = $product->get_id();

		// If margin, and we have not already applied margin,
		if ( ! isset( $product_prices[ $product_id ] ) ) {
			$margin = $product->get_meta( 'wholesale_margin' );

			if ( $margin ) {
				// Let's apply the wholesale margin.
				$price = round( $price / $margin, 2 );
			}

			// And flag, because we never want to apply double-margin.
			$product_prices[ $product_id ] = $price;
		}

		return $product_prices[ $product_id ];
	}

	public function maybe_add_wholesale_order_buttons() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if (
			is_object( $screen )
			&& in_array( $screen->base, array( 'edit', 'post' ) )
			&& 'shop_order' === $screen->post_type
		) {
			add_action( 'admin_footer', array( $this, 'add_wholesale_order_buttons' ) );
		}
	}

	public function add_wholesale_order_buttons() {
		?>
		<script type="text/javascript">
			jQuery( function( $ ) {
				$( 'a.page-title-action' ).after( '<a href="<?php echo esc_url( Menu::new_wholesale_order_url() ); ?>" class="page-title-action alignright"><?php esc_html_e( 'Add wholesale order', 'zwoowh' ); ?></a> <a href="<?php echo esc_url( add_query_arg( 'export_wholesale_backorders', 'true' ) ); ?>" class="page-title-action alignright"><?php esc_html_e( 'Export wholesale backorders', 'zwoowh' ); ?></a>' );
			});
		</script>
		<?php
	}

	public function process_backorder_export() {
		if ( ! current_user_can( 'view_woocommerce_reports' ) ) {
			return;
		}

		if ( ! isset( $_GET['export_wholesale_backorders'] ) ) {
			return;
		}

		$csv_object = self::get_rows();

		$csv = [
			[	'Product Name',
				'SKU',
				'Backorder Level'
			]
		];

		foreach ( $csv_object as $sku => $row ) {
			$csv[] = [
				$row['name'],
				$sku,
				$row['qty']
			];
		}

		self::generate_csv( $csv, 'bt_backorder_report_' . date( "m-j-Y-g-i-a" ) . '.csv' );

		exit;
	}

	private function get_rows() {
		$orders = wc_get_orders( array( 'status' => 'wc-wholesale-back', 'limit' => -1 ) );

		if ( empty( $orders ) ) {
			return array();
		}

		$csv = [];

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$sku  = $item->get_product()->get_sku();
				$name = $item->get_name();
				$qty  = $item->get_quantity();

				if ( ! isset( $csv[ $sku ] ) ) {
					$csv[ $sku ] = compact( 'name', 'qty' );
				} else {
					$csv[ $sku ]['qty'] += $qty;
				}
			}
		}

		return $csv;
	}

	private function generate_csv( $rows, $filename ) {
        header( "Content-Type: text/csv" );
        header( "Content-Disposition: attachment; filename=$filename" );

		nocache_headers();

        # Start the ouput
        $output = fopen( "php://output", "w" );

         # Then loop through the rows
        foreach ( $rows as $row ) {
            # Add the rows to the body
            fputcsv( $output, $row ); // here you can change delimiter/enclosure
        }

        # Close the stream off
        fclose( $output );

	}

	public function save_wholesale_order_meta( $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $order ) {
			$order->update_meta_data( self::$wholesale_custom_field, 'WS' );
			$order->save_meta_data();
		}
	}

	public function limit_user_search_to_wholesalers( $query ) {
		$query->set( 'role', User::ROLE );

		$users = User::get_wholesale_users();

		// Because a huge $wpdb->users.ID IN ($ids) query is probably not better for performance,
		// We'll only set the 'include' parameter for less than 300 wholesale users.
		if ( ! empty( $users ) && count( $users ) < 300 ) {
			$query->set( 'include', wp_list_pluck( $users, 'ID' ) );
		}
	}

	public static function remove_dynamic_pricing_if_wholesale( $eligible ) {
		return $eligible && ! self::is_wholesale_context();
	}

	public static function is_wholesale_context() {
		if ( null === self::$is_wholesale ) {
			self::set_is_wholesale_and_edit_mode();
		}

		return self::$is_wholesale;
	}

	public static function is_wholesale_edit_context() {
		if ( null === self::$is_edit_mode ) {
			self::set_is_wholesale_and_edit_mode();
		}

		return self::$is_edit_mode;
	}

	protected static function set_is_wholesale_and_edit_mode() {
		global $pagenow;

		self::$is_edit_mode = parent::is_wholesale( parent::get_order_being_edited() );

		self::$is_wholesale = self::$is_edit_mode || (
			'post-new.php' === $pagenow
			&& isset( $_GET['post_type'], $_GET['wholesale'] )
			&& 'shop_order' === $_GET['post_type']
		) || (
			isset( $_REQUEST['is_wholesale'] )
			&& wp_verify_nonce( $_REQUEST['is_wholesale'], __FILE__ )
		);
	}

	public static function get_wholesale_custom_field() {
		return self::$wholesale_custom_field;
	}
}
