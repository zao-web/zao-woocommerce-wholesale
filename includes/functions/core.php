<?php
namespace Zao\ZaoWooCommerce_Wholesale;

/**
 * Autoloads files with classes when needed
 *
 * @since  3.0.0
 * @param  string $class_name Name of the class being requested.
 * @return void
 */
function autoload( $class_name ) {

	// project-specific namespace prefix
	$prefix = __NAMESPACE__ . '\\';

	// does the class use the namespace prefix?
	$len = strlen( $prefix );

	if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
		// no, move to the next registered autoloader
		return;
	}

	// base directory for the namespace prefix
	$base_dir = ZWOOWH_INC . 'classes/';

	// get the relative class name
	$relative_class = substr( $class_name, $len );

	/*
	 * replace the namespace prefix with the base directory, replace namespace
	 * separators with directory separators in the relative class name, replace
	 * underscores with dashes, and append with .php
	 */
	$path = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative_class ) );
	$file = $base_dir . $path . '.php';

	// if the file exists, require it
	if ( file_exists( $file ) ) {
		require $file;
	}
}

/**
 * Default setup routine
 *
 * @uses add_action()
 * @uses do_action()
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	spl_autoload_register( $n( 'autoload' ), false );

	add_action( 'init', $n( 'i18n' ) );
	add_action( 'init', $n( 'init' ) );
	add_action( 'plugins_loaded', array( $n( 'Plugin' ), 'static_hooks' ) );

	do_action( 'zwoowh_loaded' );
}

/**
 * Registers the default textdomain.
 *
 * @uses apply_filters()
 * @uses get_locale()
 * @uses load_textdomain()
 * @uses load_plugin_textdomain()
 * @uses plugin_basename()
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'zwoowh' );
	load_textdomain( 'zwoowh', WP_LANG_DIR . '/zwoowh/zwoowh-' . $locale . '.mo' );
	load_plugin_textdomain( 'zwoowh', false, plugin_basename( ZWOOWH_PATH ) . '/languages/' );
}

/**
 * Initializes the plugin and fires an action other plugins can hook into.
 *
 * @uses do_action()
 *
 * @return void
 */
function init() {
	if ( ! defined( 'ZWOOWH_DEBUG' ) ) {
		define( 'ZWOOWH_DEBUG', false );
	}

	$zwoowh = Plugin::get_instance();

	add_action( 'zwoowh_init', array( $zwoowh, 'init' ) );

	do_action( 'zwoowh_init', $zwoowh );
}

/**
 * Activate the plugin
 *
 * @uses init()
 * @uses flush_rewrite_rules()
 *
 * @return void
 */
function activate() {
	// First load the init scripts in case any rewrite functionality is being loaded
	init();
	flush_rewrite_rules();
}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @return void
 */
function deactivate() {

}
