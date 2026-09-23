<?php
/**
 * Front-end HTML snapshot. Stored as an autoloaded option so renders
 * do not query attachments. Does not purge page/object caches.
 *
 * @package low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and reads the ticker HTML cache.
 */
class LOW_Logo_Ticker_Cache {

	const OPTION = 'low_logo_ticker_cache';
	const MAX_LOGOS = 80;
	const MAX_NAME_LENGTH = 200;

	/**
	 * Rebuild when logos or settings change.
	 */
	public static function init() {
		$callback = array( __CLASS__, 'rebuild' );

		add_action( 'update_option_' . LOW_LOGO_TICKER_OPTION, $callback );
		add_action( 'add_option_' . LOW_LOGO_TICKER_OPTION, $callback );
		add_action( 'update_option_' . LOW_LOGO_TICKER_SETTINGS_OPTION, $callback );
		add_action( 'add_option_' . LOW_LOGO_TICKER_SETTINGS_OPTION, $callback );
		add_action( 'update_option_siteurl', $callback );
		add_action( 'update_option_home', $callback );
		add_action( 'delete_attachment', array( __CLASS__, 'maybe_rebuild_on_attachment_delete' ) );
	}

	/**
	 * Cached markup, or empty string when there are no logos.
	 *
	 * @return string
	 */
	public static function get_html() {
		$cache = get_option( self::OPTION, null );

		if ( ! is_array( $cache ) || ! isset( $cache['v'] ) || $cache['v'] !== LOW_LOGO_TICKER_VERSION || ! array_key_exists( 'html', $cache ) ) {
			$cache = self::rebuild();
		}

		return isset( $cache['html'] ) ? (string) $cache['html'] : '';
	}

	/**
	 * Resolve attachment URLs once and store escaped HTML.
	 *
	 * @return array{v: string, html: string}
	 */
	public static function rebuild() {
		$logos = self::resolve_logos();
		$html  = self::build_html( $logos );
		$cache = array(
			'v'    => LOW_LOGO_TICKER_VERSION,
			'html' => $html,
		);

		update_option( self::OPTION, $cache, true );

		return $cache;
	}

	/**
	 * Rebuild only if a saved logo attachment was deleted.
	 *
	 * @param int $attachment_id Deleted attachment ID.
	 */
	public static function maybe_rebuild_on_attachment_delete( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 ) {
			return;
		}

		foreach ( low_logo_ticker_get_images() as $row ) {
			if ( is_array( $row ) && isset( $row['attachment_id'] ) && absint( $row['attachment_id'] ) === $attachment_id ) {
				self::rebuild();
				return;
			}
		}
	}

	/**
	 * Load attachment posts/meta for a set of IDs in two queries, not N.
	 *
	 * @param int[] $ids Attachment IDs.
	 */
	public static function prime_attachments( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		_prime_post_caches( $ids, false, true );
	}

	/**
	 * Whether the ID is an image (including SVG) attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_allowed_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id < 1 ) {
			return false;
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}

		$allowed = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'image/svg+xml',
		);

		return in_array( (string) $post->post_mime_type, $allowed, true );
	}

	/**
	 * Clamp a logo name.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_name( $name ) {
		$name = sanitize_text_field( $name );
		if ( strlen( $name ) > self::MAX_NAME_LENGTH ) {
			$name = substr( $name, 0, self::MAX_NAME_LENGTH );
		}

		return $name;
	}

	/**
	 * @return array<int, array{url: string, name: string}>
	 */
	private static function resolve_logos() {
		$rows = low_logo_ticker_get_images();
		$ids  = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['attachment_id'] ) ) {
				$ids[] = absint( $row['attachment_id'] );
			}
		}

		self::prime_attachments( $ids );

		$logos = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			if ( ! self::is_allowed_attachment( $attachment_id ) ) {
				continue;
			}

			$url = wp_get_attachment_image_url( $attachment_id, 'full' );
			if ( ! $url ) {
				$url = wp_get_attachment_url( $attachment_id );
			}
			if ( ! $url ) {
				continue;
			}

			$logos[] = array(
				'url'  => $url,
				'name' => isset( $row['name'] ) ? (string) $row['name'] : '',
			);

			if ( count( $logos ) >= self::MAX_LOGOS ) {
				break;
			}
		}

		return $logos;
	}

	/**
	 * @param array<int, array{url: string, name: string}> $logos Logos.
	 * @return string
	 */
	private static function build_html( $logos ) {
		if ( empty( $logos ) ) {
			return '';
		}

		$settings   = low_logo_ticker_get_settings();
		$hide_title = ! empty( $settings['hide_title_on_hover'] );
		$height     = (int) $settings['image_height'];
		$speed      = (int) $settings['speed'];
		$gap        = (int) $settings['gap'];
		$direction  = (string) $settings['direction'];
		$pause      = ! empty( $settings['pause_on_hover'] );
		$group_html = '';

		foreach ( $logos as $logo ) {
			if ( $hide_title ) {
				$group_html .= sprintf(
					'<img src="%s" class="low-logo-ticker__logo" alt="%s" decoding="async" draggable="false" />',
					esc_url( $logo['url'] ),
					esc_attr( $logo['name'] )
				);
			} else {
				$group_html .= sprintf(
					'<img src="%s" class="low-logo-ticker__logo" alt="%s" title="%s" decoding="async" draggable="false" />',
					esc_url( $logo['url'] ),
					esc_attr( $logo['name'] ),
					esc_attr( $logo['name'] )
				);
			}
		}

		$style = sprintf(
			'--low-logo-ticker-height: %dpx; --low-logo-ticker-gap: %dpx;',
			$height,
			$gap
		);

		$html  = '<div class="low-logo-ticker__wrapper" style="' . esc_attr( $style ) . '" data-speed="' . esc_attr( (string) $speed ) . '" data-direction="' . esc_attr( $direction ) . '"' . ( $pause ? ' data-pause-hover="1"' : '' ) . '>';
		$html .= '<div class="low-logo-ticker__mover">';
		$html .= '<div class="low-logo-ticker__group">' . $group_html . '</div>';
		$html .= '<div class="low-logo-ticker__group" aria-hidden="true">' . $group_html . '</div>';
		$html .= '</div></div>';

		return $html;
	}
}
