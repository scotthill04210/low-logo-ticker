<?php
/**
 * Logo Ticker settings page.
 *
 * @package low-logo-ticker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu, settings, and repeater UI.
 */
class LOW_Logo_Ticker_Admin_Page {

	const PAGE_SLUG       = 'low-logo-ticker';
	const OPTION_GROUP    = 'low_logo_ticker';
	const SETTINGS_GROUP  = 'low_logo_ticker_display';

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
			__( 'Logo Ticker Settings', 'low-logo-ticker' ),
			__( 'Logo Ticker', 'low-logo-ticker' ),
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
			LOW_LOGO_TICKER_OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize_images' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			LOW_LOGO_TICKER_SETTINGS_OPTION,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'image_height'        => LOW_LOGO_TICKER_DEFAULT_HEIGHT,
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
		LOW_Logo_Ticker_Cache::prime_attachments( $ids );

		$clean = array();

		foreach ( $input as $row ) {
			if ( count( $clean ) >= LOW_Logo_Ticker_Cache::MAX_LOGOS ) {
				break;
			}

			if ( ! is_array( $row ) ) {
				continue;
			}

			$attachment_id = isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0;
			if ( ! LOW_Logo_Ticker_Cache::is_allowed_attachment( $attachment_id ) ) {
				continue;
			}

			$clean[] = array(
				'attachment_id' => $attachment_id,
				'name'          => LOW_Logo_Ticker_Cache::sanitize_name( isset( $row['name'] ) ? $row['name'] : '' ),
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

		$height = isset( $input['image_height'] ) ? absint( $input['image_height'] ) : LOW_LOGO_TICKER_DEFAULT_HEIGHT;
		if ( $height < 8 ) {
			$height = LOW_LOGO_TICKER_DEFAULT_HEIGHT;
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
		return in_array( $tab, array( 'logos', 'settings', 'docs' ), true ) ? $tab : 'logos';
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
			'low-logo-ticker-admin',
			LOW_LOGO_TICKER_URL . 'assets/css/admin.css',
			array(),
			LOW_LOGO_TICKER_VERSION
		);

		if ( in_array( $this->get_current_tab(), array( 'settings', 'docs' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'low-logo-ticker-admin',
			LOW_LOGO_TICKER_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'media-editor' ),
			LOW_LOGO_TICKER_VERSION,
			true
		);

		wp_localize_script(
			'low-logo-ticker-admin',
			'lowLogoTickerAdmin',
			array(
				'chooseImage' => __( 'Choose Image', 'low-logo-ticker' ),
				'useImage'    => __( 'Use image', 'low-logo-ticker' ),
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

		$rows     = low_logo_ticker_get_images();
		$settings = low_logo_ticker_get_settings();
		$tab      = $this->get_current_tab();
		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap low-logo-ticker-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab<?php echo 'logos' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Logos', 'low-logo-ticker' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $base_url ) ); ?>" class="nav-tab<?php echo 'settings' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'low-logo-ticker' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'docs', $base_url ) ); ?>" class="nav-tab<?php echo 'docs' === $tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Documentation', 'low-logo-ticker' ); ?>
				</a>
			</nav>

			<?php if ( 'docs' === $tab ) : ?>
				<?php $this->render_docs(); ?>
			<?php else : ?>
			<form method="post" action="options.php">
				<?php
				if ( 'settings' === $tab ) {
					settings_fields( self::SETTINGS_GROUP );
					?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="low-logo-ticker-image-height"><?php esc_html_e( 'Image height', 'low-logo-ticker' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="low-logo-ticker-image-height"
									class="small-text"
									name="<?php echo esc_attr( LOW_LOGO_TICKER_SETTINGS_OPTION ); ?>[image_height]"
									value="<?php echo esc_attr( (string) $settings['image_height'] ); ?>"
									min="8"
									max="400"
									step="1"
								/>
								<?php esc_html_e( 'px', 'low-logo-ticker' ); ?>
								<p class="description">
									<?php esc_html_e( 'Desktop logo height. Tablet and mobile sizes scale down from this value.', 'low-logo-ticker' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Hover title', 'low-logo-ticker' ); ?></th>
							<td>
								<label for="low-logo-ticker-hide-title">
									<input
										type="checkbox"
										id="low-logo-ticker-hide-title"
										name="<?php echo esc_attr( LOW_LOGO_TICKER_SETTINGS_OPTION ); ?>[hide_title_on_hover]"
										value="1"
										<?php checked( ! empty( $settings['hide_title_on_hover'] ) ); ?>
									/>
									<?php esc_html_e( 'Hide title on hover', 'low-logo-ticker' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Removes the browser tooltip. The name is still used for the image alt text.', 'low-logo-ticker' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php
				} else {
					settings_fields( self::OPTION_GROUP );
					?>
					<input type="hidden" name="<?php echo esc_attr( LOW_LOGO_TICKER_OPTION ); ?>[-1][attachment_id]" value="0" />
					<p class="description">
						<?php
						echo esc_html__(
							'Add logos, set a name (used for alt and title), and drag to reorder. Place the ticker with the [low_logo_ticker] shortcode.',
							'low-logo-ticker'
						);
						?>
					</p>

					<div class="low-logo-ticker-rows" id="low-logo-ticker-rows">
						<?php
						if ( ! empty( $rows ) ) {
							$prime_ids = array();
							foreach ( $rows as $row ) {
								if ( is_array( $row ) && isset( $row['attachment_id'] ) ) {
									$prime_ids[] = absint( $row['attachment_id'] );
								}
							}
							LOW_Logo_Ticker_Cache::prime_attachments( $prime_ids );

							foreach ( $rows as $index => $row ) {
								$this->render_row( (int) $index, $row );
							}
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="low-logo-ticker-add">
							<?php esc_html_e( 'Add Logo', 'low-logo-ticker' ); ?>
						</button>
					</p>
					<?php
				}
				submit_button();
				?>
			</form>
			<?php endif; ?>
		</div>

		<script type="text/html" id="low-logo-ticker-row-template">
			<?php $this->render_row( '__i__', array( 'attachment_id' => 0, 'name' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * Usage instructions.
	 */
	private function render_docs() {
		?>
		<div class="low-logo-ticker-docs">
			<h2><?php esc_html_e( 'How to use Logo Ticker', 'low-logo-ticker' ); ?></h2>
			<p>
				<?php esc_html_e( 'This plugin shows a continuously scrolling row of logos. Add images here, then place the shortcode anywhere WordPress allows shortcodes.', 'low-logo-ticker' ); ?>
			</p>

			<h3><?php esc_html_e( '1. Add logos', 'low-logo-ticker' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Open the Logos tab.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Click Add Logo, then Choose Image and pick a file from the media library (JPEG, PNG, GIF, WebP, AVIF, or SVG).', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Enter a Name. That value is used for the image alt text and, unless you hide it in Settings, the hover tooltip.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Drag the handle on the left to change the scroll order. Duplicate copies a row; × removes it.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Click Save Changes. Rows without an image are discarded.', 'low-logo-ticker' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '2. Display settings', 'low-logo-ticker' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Image height sets the desktop logo height in pixels (default 40). Tablet and mobile sizes scale down from that value.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Hide title on hover removes the browser tooltip. Alt text from the Name field is kept.', 'low-logo-ticker' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Saving Settings does not change your logo list, and saving Logos does not change display settings.', 'low-logo-ticker' ); ?></p>

			<h3><?php esc_html_e( '3. Place the ticker on the site', 'low-logo-ticker' ); ?></h3>
			<p><?php esc_html_e( 'Paste this shortcode into a page, post, text widget, or a Shortcode / HTML block:', 'low-logo-ticker' ); ?></p>
			<p><code>[low_logo_ticker]</code></p>
			<p><?php esc_html_e( 'If no logos are saved, the shortcode outputs nothing.', 'low-logo-ticker' ); ?></p>

			<h3><?php esc_html_e( 'Notes', 'low-logo-ticker' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'Up to 80 logos can be saved.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'The ticker loops without a gap, even with only a few logos. Logos are not links and do not pause on hover.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Visitors who prefer reduced motion see a static, horizontally scrollable row instead of the animation.', 'low-logo-ticker' ); ?></li>
				<li><?php esc_html_e( 'Updates come from GitHub. On the Plugins screen, use Check for update.', 'low-logo-ticker' ); ?></li>
			</ul>
		</div>
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

		$field = LOW_LOGO_TICKER_OPTION . '[' . $index . ']';
		?>
		<div class="low-logo-ticker-row">
			<span class="low-logo-ticker-row__handle dashicons dashicons-menu" aria-hidden="true"></span>
			<div class="low-logo-ticker-row__preview">
				<?php if ( $thumb_url ) : ?>
					<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<button type="button" class="button low-logo-ticker-row__choose">
				<?php esc_html_e( 'Choose Image', 'low-logo-ticker' ); ?>
			</button>
			<input
				type="hidden"
				class="low-logo-ticker-row__attachment-id"
				name="<?php echo esc_attr( $field ); ?>[attachment_id]"
				value="<?php echo esc_attr( (string) $attachment_id ); ?>"
			/>
			<label class="low-logo-ticker-row__name">
				<span><?php esc_html_e( 'Name', 'low-logo-ticker' ); ?></span>
				<input
					type="text"
					class="regular-text"
					name="<?php echo esc_attr( $field ); ?>[name]"
					value="<?php echo esc_attr( $name ); ?>"
				/>
			</label>
			<div class="low-logo-ticker-row__actions">
				<button type="button" class="button-link low-logo-ticker-row__duplicate">
					<?php esc_html_e( 'Duplicate', 'low-logo-ticker' ); ?>
				</button>
				<button
					type="button"
					class="button-link low-logo-ticker-row__remove"
					aria-label="<?php esc_attr_e( 'Remove', 'low-logo-ticker' ); ?>"
				>&times;</button>
			</div>
		</div>
		<?php
	}
}
