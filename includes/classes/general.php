<?php
namespace Zao\ZaoWooCommerce_Wholesale;

class General {

	protected static $single_instance = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return General A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	protected function __construct() {

	}

	public function init() {
		add_action( 'all_admin_notices', function() {
			echo '<div id="message" class="updated"><p>';

				echo 'HOWDY';

			echo '</p></div>';
		} );
	}

}
General::get_instance();
