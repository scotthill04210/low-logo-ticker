<?php
/**
 * GitHub Releases updater for WordPress.
 *
 * Prefers a release .zip whose root folder is the plugin directory. GitHub
 * source zips (repo-tag/) are remapped so WordPress does not install into a
 * new folder and deactivate the plugin.
 *
 * @package low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires LOW Logo Ticker into the WordPress update API.
 */
class LOW_Logo_Ticker_GitHub_Updater {

	const REPO      = 'scotthill04210/low-logo-ticker';
	const SLUG      = 'low-logo-ticker';
	const MAIN_FILE = 'low-logo-ticker.php';
	const CACHE_KEY = 'low_logo_ticker_github_release';
	const CACHE_TTL = 43200; // 12 hours.

	/**
	 * Plugin basename, e.g. LOW-logo-ticker/low-logo-ticker.php.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Installed directory name (preserves LOW-logo-ticker vs low-logo-ticker).
	 *
	 * @var string
	 */
	private $plugin_dir;

	/**
	 * Register update hooks.
	 */
	public static function init() {
		new self();
	}

	/**
	 * Hook WordPress update filters.
	 */
	public function __construct() {
		$this->plugin_basename = plugin_basename( LOW_LOGO_TICKER_FILE );
		$this->plugin_dir      = dirname( $this->plugin_basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_post_low_logo_ticker_check_update', array( $this, 'handle_check_update' ) );
		add_action( 'admin_notices', array( $this, 'render_check_update_notice' ) );
	}

	/**
	 * Settings and Check for update links on plugins.php.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		$extra = array();

		if ( current_user_can( 'manage_options' ) ) {
			$extra['settings'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=low-logo-ticker' ) ),
				esc_html__( 'Settings', 'low-logo-ticker' )
			);
		}

		if ( current_user_can( 'update_plugins' ) ) {
			$extra['check_update'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin-post.php?action=low_logo_ticker_check_update' ),
						'low_logo_ticker_check_update'
					)
				),
				esc_html__( 'Check for update', 'low-logo-ticker' )
			);
		}

		return array_merge( $extra, $links );
	}

	/**
	 * Clear caches and force a GitHub check.
	 */
	public function handle_check_update() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update plugins for this site.', 'low-logo-ticker' ) );
		}

		check_admin_referer( 'low_logo_ticker_check_update' );

		delete_transient( self::CACHE_KEY );
		delete_site_transient( self::CACHE_KEY );

		$updates = get_site_transient( 'update_plugins' );
		if ( ! is_object( $updates ) ) {
			$updates = new stdClass();
		}
		if ( empty( $updates->checked ) || ! is_array( $updates->checked ) ) {
			$updates->checked = array();
		}
		$updates->checked[ $this->plugin_basename ] = LOW_LOGO_TICKER_VERSION;
		if ( isset( $updates->response ) && is_array( $updates->response ) ) {
			unset( $updates->response[ $this->plugin_basename ] );
		}
		if ( isset( $updates->no_update ) && is_array( $updates->no_update ) ) {
			unset( $updates->no_update[ $this->plugin_basename ] );
		}
		set_site_transient( 'update_plugins', $this->check_for_update( $updates ) );

		$release = $this->get_latest_release();
		$status  = 'unavailable';

		if ( is_array( $release ) && ! empty( $release['version'] ) ) {
			$status = version_compare( $release['version'], LOW_LOGO_TICKER_VERSION, '>' ) ? 'available' : 'current';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'low_logo_ticker_update_check' => $status,
					'low_logo_ticker_remote'       => is_array( $release ) ? rawurlencode( (string) ( $release['version'] ?? '' ) ) : '',
				),
				admin_url( 'plugins.php' )
			)
		);
		exit;
	}

	/**
	 * Notice after a manual update check.
	 */
	public function render_check_update_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$status = isset( $_GET['low_logo_ticker_update_check'] ) ? sanitize_key( wp_unslash( (string) $_GET['low_logo_ticker_update_check'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $status ) {
			return;
		}

		$remote = isset( $_GET['low_logo_ticker_remote'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['low_logo_ticker_remote'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'available' === $status ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: installed version, 2: available version */
						__( 'LOW Logo Ticker %1$s — update %2$s is available. Use the update link below the plugin name.', 'low-logo-ticker' ),
						LOW_LOGO_TICKER_VERSION,
						$remote ? $remote : __( 'a newer version', 'low-logo-ticker' )
					)
				)
			);
			return;
		}

		if ( 'current' === $status ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: installed version */
						__( 'LOW Logo Ticker %s is up to date.', 'low-logo-ticker' ),
						LOW_LOGO_TICKER_VERSION
					)
				)
			);
			return;
		}

		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html__( 'Could not check GitHub for LOW Logo Ticker updates. Try again later.', 'low-logo-ticker' )
		);
	}

	/**
	 * Inject update data when GitHub has a newer release.
	 *
	 * @param object|null $transient Update transient.
	 * @return object|null
	 */
	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $release['version'];
		if ( ! $remote_version || ! version_compare( $remote_version, LOW_LOGO_TICKER_VERSION, '>' ) ) {
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'slug'         => $this->plugin_dir,
			'plugin'       => $this->plugin_basename,
			'new_version'  => $remote_version,
			'url'          => $release['html_url'],
			'package'      => $release['download_url'],
			'icons'        => array(),
			'banners'      => array(),
			'tested'       => '',
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);

		return $transient;
	}

	/**
	 * Plugin information modal.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
			return $result;
		}

		$slug = isset( $args->slug ) ? (string) $args->slug : '';
		if ( self::SLUG !== $slug && $this->plugin_dir !== $slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$changelog = ! empty( $release['body'] )
			? wp_kses_post( wpautop( $release['body'] ) )
			: '<p>' . esc_html__( 'See the GitHub release notes for details.', 'low-logo-ticker' ) . '</p>';

		return (object) array(
			'name'          => 'LOW Logo Ticker',
			'slug'          => $this->plugin_dir,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/scotthill04210">Scott Hill</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'download_link' => $release['download_url'],
			'trunk'         => $release['download_url'],
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Animated logo ticker that loops without a gap.', 'low-logo-ticker' ) . '</p>',
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Rename the extracted zip so WordPress overwrites the installed folder.
	 *
	 * @param string       $source        Extracted source with trailing slash.
	 * @param string       $remote_source Upgrade working directory.
	 * @param \WP_Upgrader $upgrader      Upgrader.
	 * @param array        $hook_extra    Extra hook data.
	 * @return string|\WP_Error
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wp_filesystem;

		if ( empty( $source ) || empty( $remote_source ) || ! is_object( $wp_filesystem ) ) {
			return $source;
		}

		$explicit = $this->is_our_upgrade( is_array( $hook_extra ) ? $hook_extra : array() );
		if ( ! $explicit ) {
			return $source;
		}

		$found = $this->find_plugin_root( (string) $source );

		if ( ! $found ) {
			return new WP_Error(
				'low_logo_ticker_upgrade_missing_main_file',
				__( 'The update package did not contain low-logo-ticker.php.', 'low-logo-ticker' )
			);
		}

		$found      = trailingslashit( $found );
		$desired    = trailingslashit( $remote_source ) . $this->plugin_dir . '/';
		$found_base = basename( untrailingslashit( $found ) );

		if ( $this->plugin_dir === $found_base && untrailingslashit( $found ) === untrailingslashit( $desired ) ) {
			return $found;
		}

		if ( $wp_filesystem->exists( $desired ) && untrailingslashit( $found ) !== untrailingslashit( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( untrailingslashit( $found ) === untrailingslashit( $desired ) ) {
			return $desired;
		}

		if ( $wp_filesystem->move( $found, $desired ) ) {
			return $desired;
		}

		if ( function_exists( 'copy_dir' ) && copy_dir( $found, $desired ) ) {
			$wp_filesystem->delete( $found, true );
			return $desired;
		}

		return new WP_Error(
			'low_logo_ticker_upgrade_rename_failed',
			__( 'Could not move the update package into the plugin directory.', 'low-logo-ticker' )
		);
	}

	/**
	 * Whether this upgrade is for LOW Logo Ticker.
	 *
	 * @param array $hook_extra Extra hook data.
	 * @return bool
	 */
	private function is_our_upgrade( $hook_extra ) {
		$plugin = isset( $hook_extra['plugin'] ) ? (string) $hook_extra['plugin'] : '';
		if ( $plugin ) {
			return $plugin === $this->plugin_basename;
		}

		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			return in_array( $this->plugin_basename, $hook_extra['plugins'], true );
		}

		return false;
	}

	/**
	 * Directory that contains low-logo-ticker.php.
	 *
	 * @param string $source Extracted source path.
	 * @return string|null
	 */
	private function find_plugin_root( $source ) {
		global $wp_filesystem;

		$source = untrailingslashit( $source );
		$main   = self::MAIN_FILE;

		if ( $wp_filesystem->exists( $source . '/' . $main ) ) {
			return $source;
		}

		$dirlist = $wp_filesystem->dirlist( $source );
		if ( ! is_array( $dirlist ) ) {
			return null;
		}

		foreach ( $dirlist as $name => $entry ) {
			if ( empty( $entry['type'] ) || 'd' !== $entry['type'] ) {
				continue;
			}

			$candidate = $source . '/' . $name;
			if ( $wp_filesystem->exists( $candidate . '/' . $main ) ) {
				return $candidate;
			}

			$nested = $wp_filesystem->dirlist( $candidate );
			if ( ! is_array( $nested ) ) {
				continue;
			}

			foreach ( $nested as $nested_name => $nested_entry ) {
				if ( empty( $nested_entry['type'] ) || 'd' !== $nested_entry['type'] ) {
					continue;
				}

				$deep = $candidate . '/' . $nested_name;
				if ( $wp_filesystem->exists( $deep . '/' . $main ) ) {
					return $deep;
				}
			}
		}

		return null;
	}

	/**
	 * Clear release cache after this plugin updates.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader.
	 * @param array        $options  Options.
	 */
	public function clear_cache( $upgrader, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		$plugins = array();
		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$plugins = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$plugins = array( $options['plugin'] );
		}

		if ( $plugins && ! in_array( $this->plugin_basename, $plugins, true ) ) {
			return;
		}

		delete_transient( self::CACHE_KEY );
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Latest stable GitHub release.
	 *
	 * @return array{version:string,download_url:string,html_url:string,body:string,published_at:string}|null
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return is_array( $cached ) && ! empty( $cached['version'] ) ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, 'unavailable', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || ! empty( $data['prerelease'] ) || ! empty( $data['draft'] ) ) {
			set_site_transient( self::CACHE_KEY, 'unavailable', HOUR_IN_SECONDS );
			return null;
		}

		$version = $this->normalize_version( (string) ( $data['tag_name'] ?? '' ) );
		if ( '' === $version ) {
			set_site_transient( self::CACHE_KEY, 'unavailable', HOUR_IN_SECONDS );
			return null;
		}

		$download_url = $this->pick_download_url( $data );
		if ( '' === $download_url ) {
			set_site_transient( self::CACHE_KEY, 'unavailable', HOUR_IN_SECONDS );
			return null;
		}

		$html_url = (string) ( $data['html_url'] ?? '' );
		if ( ! $this->is_github_page_url( $html_url ) ) {
			$html_url = 'https://github.com/' . self::REPO . '/releases';
		}

		$release = array(
			'version'      => $version,
			'download_url' => $download_url,
			'html_url'     => $html_url,
			'body'         => (string) ( $data['body'] ?? '' ),
			'published_at' => (string) ( $data['published_at'] ?? '' ),
		);

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Prefer a plugin-named .zip asset over the GitHub source archive.
	 *
	 * @param array<string, mixed> $data Release payload.
	 * @return string
	 */
	private function pick_download_url( $data ) {
		$assets = isset( $data['assets'] ) && is_array( $data['assets'] ) ? $data['assets'] : array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( '' === $url || ! preg_match( '/\.zip$/i', $name ) || ! $this->is_allowed_package_url( $url ) ) {
				continue;
			}

			if ( preg_match( '/low-?logo-?ticker/i', $name ) ) {
				return $url;
			}
		}

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$name = (string) ( $asset['name'] ?? '' );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );

			if ( $url && preg_match( '/\.zip$/i', $name ) && $this->is_allowed_package_url( $url ) ) {
				return $url;
			}
		}

		$tag = (string) ( $data['tag_name'] ?? '' );
		if ( '' === $tag ) {
			return '';
		}

		return 'https://github.com/' . self::REPO . '/archive/refs/tags/' . rawurlencode( $tag ) . '.zip';
	}

	/**
	 * Only accept package downloads from GitHub hosts.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	/**
	 * GitHub HTML pages only (release notes, repo).
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private function is_github_page_url( $url ) {
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'https', 'http' ), true ) ) {
			return false;
		}
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		return in_array( strtolower( $host ), array( 'github.com', 'www.github.com' ), true );
	}

	private function is_allowed_package_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$host    = strtolower( $host );
		$allowed = array(
			'github.com',
			'www.github.com',
			'objects.githubusercontent.com',
			'release-assets.githubusercontent.com',
			'codeload.github.com',
		);

		return in_array( $host, $allowed, true );
	}

	/**
	 * Strip a leading "v" from tag names.
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	private function normalize_version( $tag ) {
		$tag = trim( $tag );
		if ( '' === $tag ) {
			return '';
		}

		if ( 0 === stripos( $tag, 'v' ) && isset( $tag[1] ) && is_numeric( $tag[1] ) ) {
			$tag = substr( $tag, 1 );
		}

		return $tag;
	}
}
