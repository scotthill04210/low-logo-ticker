<?php
/**
 * Front-end shortcode and ticker markup.
 *
 * @package low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers [low_logo_ticker] and prints cached markup.
 */
class LOW_Logo_Ticker_Shortcode {

	const SHORTCODE    = 'low_logo_ticker';
	const STYLE_HANDLE = 'low-logo-ticker';

	/**
	 * Hook front-end actions.
	 */
	public static function init() {
		$instance = new self();
		add_shortcode( self::SHORTCODE, array( $instance, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $instance, 'register_and_maybe_enqueue' ) );
	}

	/**
	 * Register ticker CSS; enqueue only when the current post contains the shortcode.
	 */
	public function register_and_maybe_enqueue() {
		wp_register_style(
			self::STYLE_HANDLE,
			LOW_LOGO_TICKER_URL . 'assets/css/ticker.css',
			array(),
			LOW_LOGO_TICKER_VERSION
		);

		$post = get_post();
		if ( is_singular() && $post && isset( $post->post_content ) && has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public function render() {
		$html = LOW_Logo_Ticker_Cache::get_html();
		if ( '' === $html ) {
			return '';
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		return $html;
	}
}
