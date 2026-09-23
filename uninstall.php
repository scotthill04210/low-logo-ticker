<?php
/**
 * Remove plugin options and update-check cache.
 *
 * @package low-logo-ticker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$options = array(
	'low_logo_ticker_images',
	'low_logo_ticker_settings',
	'low_logo_ticker_cache',
);

foreach ( $options as $option ) {
	delete_option( $option );
	if ( function_exists( 'delete_site_option' ) ) {
		delete_site_option( $option );
	}
}

delete_transient( 'low_logo_ticker_github_release' );
delete_site_transient( 'low_logo_ticker_github_release' );
