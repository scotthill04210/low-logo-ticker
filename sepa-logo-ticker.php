<?php
/**
 * Plugin Name: SEPA Logo Ticker
 * Description: Animated logo ticker/marquee
 * Version: 1.0.6
 * Author: Scott Hill
 * Text Domain: sepa-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEPA_LOGO_TICKER_VERSION', '1.0.6' );
define( 'SEPA_LOGO_TICKER_FILE', __FILE__ );
define( 'SEPA_LOGO_TICKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'SEPA_LOGO_TICKER_URL', plugin_dir_url( __FILE__ ) );
define( 'SEPA_LOGO_TICKER_OPTION', 'sepa_logo_ticker_images' );
define( 'SEPA_LOGO_TICKER_SETTINGS_OPTION', 'sepa_logo_ticker_settings' );
define( 'SEPA_LOGO_TICKER_DEFAULT_HEIGHT', 40 );

require_once SEPA_LOGO_TICKER_PATH . 'includes/class-cache.php';
require_once SEPA_LOGO_TICKER_PATH . 'includes/class-shortcode.php';

if ( is_admin() ) {
	require_once SEPA_LOGO_TICKER_PATH . 'includes/class-admin-page.php';
}

/**
 * Saved logo rows in display order.
 *
 * @return array<int, array{attachment_id: int, name: string}>
 */
function sepa_logo_ticker_get_images() {
	$rows = get_option( SEPA_LOGO_TICKER_OPTION, array() );

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
function sepa_logo_ticker_get_settings() {
	$settings = get_option( SEPA_LOGO_TICKER_SETTINGS_OPTION, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$height = isset( $settings['image_height'] ) ? absint( $settings['image_height'] ) : SEPA_LOGO_TICKER_DEFAULT_HEIGHT;
	if ( $height < 8 ) {
		$height = SEPA_LOGO_TICKER_DEFAULT_HEIGHT;
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
function sepa_logo_ticker_get_image_height() {
	$settings = sepa_logo_ticker_get_settings();
	return (int) $settings['image_height'];
}

add_action(
	'plugins_loaded',
	static function () {
		SEPA_Logo_Ticker_Cache::init();
		SEPA_Logo_Ticker_Shortcode::init();

		if ( is_admin() ) {
			SEPA_Logo_Ticker_Admin_Page::init();
		}
	}
);
