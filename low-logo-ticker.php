<?php
/**
 * Plugin Name: LOW Logo Ticker
 * Plugin URI: https://github.com/scotthill04210/low-logo-ticker
 * Description: Animated logo ticker/marquee
 * Version: 1.0.11
 * Author: Scott Hill
 * Text Domain: low-logo-ticker
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/scotthill04210/low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOW_LOGO_TICKER_VERSION', '1.0.11' );
define( 'LOW_LOGO_TICKER_FILE', __FILE__ );
define( 'LOW_LOGO_TICKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'LOW_LOGO_TICKER_URL', plugin_dir_url( __FILE__ ) );
define( 'LOW_LOGO_TICKER_OPTION', 'low_logo_ticker_images' );
define( 'LOW_LOGO_TICKER_SETTINGS_OPTION', 'low_logo_ticker_settings' );
define( 'LOW_LOGO_TICKER_DEFAULT_HEIGHT', 40 );
define( 'LOW_LOGO_TICKER_DEFAULT_SPEED', 70 );
define( 'LOW_LOGO_TICKER_DEFAULT_GAP', 48 );

require_once LOW_LOGO_TICKER_PATH . 'includes/class-cache.php';
require_once LOW_LOGO_TICKER_PATH . 'includes/class-shortcode.php';

if ( is_admin() ) {
	require_once LOW_LOGO_TICKER_PATH . 'includes/class-admin-page.php';
	require_once LOW_LOGO_TICKER_PATH . 'includes/class-github-updater.php';
} elseif ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
	require_once LOW_LOGO_TICKER_PATH . 'includes/class-github-updater.php';
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
 * Normalize saved or submitted display settings.
 *
 * @param mixed $settings Raw settings.
 * @return array{image_height: int, hide_title_on_hover: bool, speed: int, direction: string, pause_on_hover: bool, gap: int}
 */
function low_logo_ticker_normalize_settings( $settings ) {
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	$height = isset( $settings['image_height'] ) ? absint( $settings['image_height'] ) : LOW_LOGO_TICKER_DEFAULT_HEIGHT;
	if ( $height < 8 ) {
		$height = LOW_LOGO_TICKER_DEFAULT_HEIGHT;
	}

	$speed = isset( $settings['speed'] ) ? absint( $settings['speed'] ) : LOW_LOGO_TICKER_DEFAULT_SPEED;
	$speed = min( 400, max( 10, $speed ) );

	$direction = isset( $settings['direction'] ) ? sanitize_key( $settings['direction'] ) : 'left';
	if ( ! in_array( $direction, array( 'left', 'right' ), true ) ) {
		$direction = 'left';
	}

	$gap = isset( $settings['gap'] ) ? absint( $settings['gap'] ) : LOW_LOGO_TICKER_DEFAULT_GAP;
	$gap = min( 200, max( 8, $gap ) );

	return array(
		'image_height'        => min( 400, $height ),
		'hide_title_on_hover' => ! empty( $settings['hide_title_on_hover'] ),
		'speed'               => $speed,
		'direction'           => $direction,
		'pause_on_hover'      => ! empty( $settings['pause_on_hover'] ),
		'gap'                 => $gap,
	);
}

/**
 * Display and animation settings.
 *
 * @return array{image_height: int, hide_title_on_hover: bool, speed: int, direction: string, pause_on_hover: bool, gap: int}
 */
function low_logo_ticker_get_settings() {
	return low_logo_ticker_normalize_settings( get_option( LOW_LOGO_TICKER_SETTINGS_OPTION, array() ) );
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

		if ( class_exists( 'LOW_Logo_Ticker_GitHub_Updater', false ) ) {
			LOW_Logo_Ticker_GitHub_Updater::init();
		}

		if ( is_admin() ) {
			LOW_Logo_Ticker_Admin_Page::init();
		}
	}
);
