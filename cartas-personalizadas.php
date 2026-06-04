<?php
/**
 * Plugin Name: Cartas Personalizadas
 * Plugin URI:  https://menes.studio
 * Description: Plugin para vender cartas personalizadas con WooCommerce.
 * Version:     1.0.0
 * Author:      menes.studio
 * Text Domain: cartas-personalizadas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once CP_PLUGIN_PATH . 'includes/class-cp-admin.php';
require_once CP_PLUGIN_PATH . 'includes/class-cp-frontend.php';
require_once CP_PLUGIN_PATH . 'includes/class-cp-pdf.php';
require_once CP_PLUGIN_PATH . 'includes/class-cp-templates.php';
require_once CP_PLUGIN_PATH . 'includes/class-cp-orders.php';

class Cartas_Personalizadas {

	public function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		// Initialize Admin
		new CP_Admin();
		
		// Initialize Templates
		new CP_Templates();

		// Initialize Frontend
		new CP_Frontend();

		// Initialize PDF Engine
		new CP_PDF();

		// Initialize Orders
		new CP_Orders();
	}
}

// Start the plugin
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        new Cartas_Personalizadas();
    }
});
