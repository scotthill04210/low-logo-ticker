<?php
/**
 * Plugin Name: LOW Logo Ticker
 * Plugin URI: https://github.com/scotthill04210/low-logo-ticker
 * Description: Animated logo ticker/marquee
 * Version: 1.0.8
 * Author: Scott Hill
 * Text Domain: low-logo-ticker
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/scotthill04210/low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOW_LOGO_TICKER_VERSION', '1.0.8' );
define( 'LOW_LOGO_TICKER_FILE', __FILE__ );
define( 'LOW_LOGO_TICKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOW_LOGO_TICKER_URL', plugin_dir_url( __FILE__ ) );
define( 'LOW_LOGO_TICKER_OPTION', 'low_logo_ticker_images' );
define( 'LOW_LOGO_TICKER_SETTINGS_OPTION', 'low_logo_ticker_settings' );
define( 'LOW_LOGO_TICKER_DEFAULT_HEIGHT', 40 );

require_once LOW_LOGO_TICKER_PATH . 'includes/class-cache.php';
require_once LOW_LOGO_TICKER_PATH . 'includes/class-shortcode.php';
require_once LOW_LOGO_TICKER_PATH . 'includes/class-github-updater.php';

if ( is_admin() ) {
	require_once LOW_LOGO_TICKER_PATH . 'includes/class-admin-page.php';
}

/**
 * Saved logo rows in display order.
 *
 * @return array<int, array{attachment_id: int, name: string}>
 */
function low_logo_ticker_get_images() {
	$rows = get_option( LOW_LOGO_TICKER_OPTION, array() );

	if ( ! is_array( $rows ) ) {
		return array();
	}

	return $rows;
}

/**
 * Display settings, including image height.
 *
 * @return array{image_height: int, hide_title_on_hover: bool}
 */
function low_logo_ticker_get_settings() {
	$settings = get_option( LOW_LOGO_TICKER_SETTINGS_OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$height = isset( $settings['image_height'] ) ? absint( $settings['image_height'] ) : LOW_LOGO_TICKER_DEFAULT_HEIGHT;
	if ( $height < 8 ) {
		$height = LOW_LOGO_TICKER_DEFAULT_HEIGHT;
	}

	return array(
		'image_height'        => min( 400, $height ),
		'hide_title_on_hover' => ! empty( $settings['hide_title_on_hover'] ),
	);
}

/**
 * Front-end logo height in pixels.
 *
 * @return int
 */
function low_logo_ticker_get_image_height() {
	$settings = low_logo_ticker_get_settings();
	return (int) $settings['image_height'];
}

add_action(
	'plugins_loaded',
	static function () {
		LOW_Logo_Ticker_Cache::init();
		LOW_Logo_Ticker_Shortcode::init();
		LOW_Logo_Ticker_GitHub_Updater::init();

		if ( is_admin() ) {
			LOW_Logo_Ticker_Admin_Page::init();
		}
	}
);
