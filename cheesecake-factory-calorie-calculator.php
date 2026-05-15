<?php
/**
 * Plugin Name:       Cheesecake Factory Calorie Calculator
 * Plugin URI:        https://example.com/
 * Description:       Frontend calorie calculator with CSV import for Cheesecake Factory menu data. Shortcode: [cheesecake_factory_calorie_calculator]
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cheesecake-factory-calorie-calculator
 * Domain Path:       /languages
 *
 * @package Cheesecake_Factory_Calorie_Calculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CFC_VERSION', '1.0.3' );
define( 'CFC_PLUGIN_FILE', __FILE__ );
define( 'CFC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once CFC_PLUGIN_DIR . 'includes/class-cfc-database.php';
require_once CFC_PLUGIN_DIR . 'includes/class-cfc-importer.php';
require_once CFC_PLUGIN_DIR . 'includes/class-cfc-admin.php';
require_once CFC_PLUGIN_DIR . 'includes/class-cfc-shortcode.php';
require_once CFC_PLUGIN_DIR . 'includes/class-cfc-plugin.php';

/**
 * Bootstrap.
 */
function cfc_run() {
	return CFC_Plugin::instance();
}

cfc_run();

register_activation_hook( __FILE__, array( 'CFC_Database', 'activate' ) );
