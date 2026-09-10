<?php
/**
 * Logo Ticker settings page.
 *
 * @package sepa-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu, settings, and repeater UI.
 */
class SEPA_Logo_Ticker_Admin_Page {

	const PAGE_SLUG       = 'sepa-logo-ticker';
	const OPTION_GROUP    = 'sepa_logo_ticker';
	const SETTINGS_GROUP  = 'sepa_logo_ticker_display';

	/**
	 * Hook admin actions.
	 */
	public static function init() {
		$instance = new self();
		add_action( 'admin_menu', array( $instance, 'register_menu' ) );
		add_action( 'admin_init', array( $instance, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $instance, 'enqueue_assets' ) );
	}

	/**
	 * Top-level Logo Ticker menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Logo Ticker Settings', 'sepa-logo-ticker' ),
			__( 'Logo Ticker', 'sepa-logo-ticker' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-images-alt2',
			3
		);
	}

	/**
	 * Settings API registration.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			SEPA_LOGO_TICKER_OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize_images' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			SEPA_LOGO_TICKER_SETTINGS_OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'image_height'        => SEPA_LOGO_TICKER_DEFAULT_HEIGHT,
					'hide_title_on_hover' => false,
				),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize repeater rows. Drops rows with no image.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<int, array{attachment_id: int, name: string}>
	 */
	public function sanitize_images( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$ids = array();
		foreach ( $input as $row ) {
			if ( is_array( $row ) && isset( $row['attachment_id'] ) ) {
				$ids[] = absint( $row['attachment_id'] );
			}
		}
		SEPA_Logo_Ticker_Cache::prime_attachments( $ids );

		$clean = array();

		foreach ( $input as $row ) {
			if ( count( $clean ) >= SEPA_Logo_Ticker_Cache::MAX_LOGOS ) {
				break;
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			if ( ! SEPA_Logo_Ticker_Cache::is_allowed_attachment( $attachment_id ) ) {
				continue;
			}

			$clean[] = array(
				'attachment_id' => $attachment_id,
				'name'          => SEPA_Logo_Ticker_Cache::sanitize_name( isset( $row['name'] ) ? $row['name'] : '' ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitize display settings.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array{image_height: int, hide_title_on_hover: bool}
	 */
	public function sanitize_settings( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$height = isset( $input['image_height'] ) ? absint( $input['image_height'] ) : SEPA_LOGO_TICKER_DEFAULT_HEIGHT;
		if ( $height < 8 ) {
			$height = SEPA_LOGO_TICKER_DEFAULT_HEIGHT;
		}

		return array(
			'image_height'        => min( 400, $height ),
			'hide_title_on_hover' => ! empty( $input['hide_title_on_hover'] ),
		);
	}

	/**
	 * Current admin tab.
	 *
	 * @return string
	 */
	private function get_current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'logos';
		return in_array( $tab, array( 'logos', 'settings' ), true ) ? $tab : 'logos';
	}

	/**
	 * Enqueue admin assets only on this page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'sepa-logo-ticker-admin',
			SEPA_LOGO_TICKER_URL . 'assets/css/admin.css',
			array(),
			SEPA_LOGO_TICKER_VERSION
		);

		if ( 'settings' === $this->get_current_tab() ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'sepa-logo-ticker-admin',
			SEPA_LOGO_TICKER_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'media-editor' ),
			SEPA_LOGO_TICKER_VERSION,
			true
		);

		wp_localize_script(
			'sepa-logo-ticker-admin',
			'sepaLogoTickerAdmin',
			array(
				'chooseImage' => __( 'Choose Image', 'sepa-logo-ticker' ),
				'useImage'    => __( 'Use image', 'sepa-logo-ticker' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rows     = sepa_logo_ticker_get_images();
		$settings = sepa_logo_ticker_get_settings();
		$tab      = $this->get_current_tab();
		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap sepa-logo-ticker-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab<?php echo 'logos' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logos', 'sepa-logo-ticker' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'sepa-logo-ticker' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php">
				<?php
				if ( 'settings' === $tab ) {
					settings_fields( self::SETTINGS_GROUP );
					?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="sepa-logo-ticker-image-height"><?php esc_html_e( 'Image height', 'sepa-logo-ticker' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="sepa-logo-ticker-image-height"
									class="small-text"
									name="<?php echo esc_attr( SEPA_LOGO_TICKER_SETTINGS_OPTION ); ?>[image_height]"
									value="<?php echo esc_attr( (string) $settings['image_height'] ); ?>"
									min="8"
									max="400"
									step="1"
								/>
								<?php esc_html_e( 'px', 'sepa-logo-ticker' ); ?>
								<p class="description">
									<?php esc_html_e( 'Desktop logo height. Tablet and mobile sizes scale down from this value.', 'sepa-logo-ticker' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Hover title', 'sepa-logo-ticker' ); ?></th>
							<td>
								<label for="sepa-logo-ticker-hide-title">
									<input
										type="checkbox"
										id="sepa-logo-ticker-hide-title"
										name="<?php echo esc_attr( SEPA_LOGO_TICKER_SETTINGS_OPTION ); ?>[hide_title_on_hover]"
										value="1"
										<?php checked( ! empty( $settings['hide_title_on_hover'] ) ); ?>
									/>
									<?php esc_html_e( 'Hide title on hover', 'sepa-logo-ticker' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Removes the browser tooltip. The name is still used for the image alt text.', 'sepa-logo-ticker' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php
				} else {
					settings_fields( self::OPTION_GROUP );
					?>
					<input type="hidden" name="<?php echo esc_attr( SEPA_LOGO_TICKER_OPTION ); ?>[-1][attachment_id]" value="0" />
					<p class="description">
						<?php
						echo esc_html__(
							'Add logos, set a name (used for alt and title), and drag to reorder. Place the ticker with the [sepa_logo_ticker] shortcode.',
							'sepa-logo-ticker'
						);
						?>
					</p>

					<div class="sepa-logo-ticker-rows" id="sepa-logo-ticker-rows">
						<?php
						if ( ! empty( $rows ) ) {
							$prime_ids = array();
							foreach ( $rows as $row ) {
								if ( is_array( $row ) && isset( $row['attachment_id'] ) ) {
									$prime_ids[] = absint( $row['attachment_id'] );
								}
							}
							SEPA_Logo_Ticker_Cache::prime_attachments( $prime_ids );

							foreach ( $rows as $index => $row ) {
								$this->render_row( (int) $index, $row );
							}
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="sepa-logo-ticker-add">
							<?php esc_html_e( 'Add Logo', 'sepa-logo-ticker' ); ?>
						</button>
					</p>
					<?php
				}
				submit_button();
				?>
			</form>
		</div>

		<script type="text/html" id="sepa-logo-ticker-row-template">
			<?php $this->render_row( '__i__', array( 'attachment_id' => 0, 'name' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single repeater row.
	 *
	 * @param int|string                             $index Row index or template placeholder.
	 * @param array{attachment_id?:int,name?:string} $row   Row data.
	 */
	private function render_row( $index, $row ) {
		$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
		$name          = isset( $row['name'] ) ? $row['name'] : '';
		$thumb_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		if ( ! $thumb_url && $attachment_id ) {
			$thumb_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		}

		$field = SEPA_LOGO_TICKER_OPTION . '[' . $index . ']';
		?>
		<div class="sepa-logo-ticker-row">
			<span class="sepa-logo-ticker-row__handle dashicons dashicons-menu" aria-hidden="true"></span>
			<div class="sepa-logo-ticker-row__preview">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<button type="button" class="button sepa-logo-ticker-row__choose">
				<?php esc_html_e( 'Choose Image', 'sepa-logo-ticker' ); ?>
			</button>
			<input
				type="hidden"
				class="sepa-logo-ticker-row__attachment-id"
				name="<?php echo esc_attr( $field ); ?>[attachment_id]"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
			/>
			<label class="sepa-logo-ticker-row__name">
				<span><?php esc_html_e( 'Name', 'sepa-logo-ticker' ); ?></span>
				<input
					type="text"
					class="regular-text"
					name="<?php echo esc_attr( $field ); ?>[name]"
					value="<?php echo esc_attr( $name ); ?>"
				/>
			</label>
			<div class="sepa-logo-ticker-row__actions">
				<button type="button" class="button-link sepa-logo-ticker-row__duplicate">
					<?php esc_html_e( 'Duplicate', 'sepa-logo-ticker' ); ?>
				</button>
				<button
					type="button"
					class="button-link sepa-logo-ticker-row__remove"
					aria-label="<?php esc_attr_e( 'Remove', 'sepa-logo-ticker' ); ?>"
				>&times;</button>
			</div>
		</div>
		<?php
	}
}
