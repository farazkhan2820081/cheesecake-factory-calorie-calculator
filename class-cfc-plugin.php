<?php
/**
 * Main plugin bootstrap.
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CFC_Plugin
 */
class CFC_Plugin {

	/**
	 * Instance.
	 *
	 * @var CFC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return CFC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * CFC_Plugin constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( 'CFC_Database', 'maybe_install' ), 5 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		CFC_Admin::instance();
		CFC_Shortcode::instance();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'cheesecake-factory-calorie-calculator',
			false,
			dirname( CFC_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
