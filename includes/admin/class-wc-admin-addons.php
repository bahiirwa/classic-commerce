<?php
/**
 * Addons Page
 *
 * @author   WooThemes
 * @category Admin
 * @package  WooCommerce/Admin
 * @version  2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Admin_Addons Class.
 */
class WC_Admin_Addons {

	/**
	 * Get featured for the addons screen
	 *
	 * @return array of objects
	 */
	public static function get_featured() {
		if ( false === ( $featured = get_transient( 'wc_addons_featured' ) ) ) {
			$raw_featured = wp_safe_remote_get( 'https://d3t0oesq8995hv.cloudfront.net/add-ons/featured-v2.json', array( 'user-agent' => 'WooCommerce Addons Page' ) );
			if ( ! is_wp_error( $raw_featured ) ) {
				$featured = json_decode( wp_remote_retrieve_body( $raw_featured ) );
				if ( $featured ) {
					set_transient( 'wc_addons_featured', $featured, WEEK_IN_SECONDS );
				}
			}
		}

		if ( is_object( $featured ) ) {
			self::output_featured_sections( $featured->sections );
			return $featured;
		}
	}

	/**
	 * Build url parameter string
	 *
	 * @param  string $category
	 * @param  string $term
	 * @param  string $country
	 *
	 * @return string url parameter string
	 */
	public static function build_parameter_string( $category, $term, $country ) {

		$paramters = array(
			'category' => $category,
			'term'     => $term,
			'country'  => $country,
		);

		return '?' . http_build_query( $paramters );
	}

	/**
	 * Call API to get extensions
	 *
	 * @param  string $category
	 * @param  string $term
	 * @param  string $country
	 *
	 * @return array of extensions
	 */
	public static function get_extension_data( $category, $term, $country ) {
		$parameters     = self::build_parameter_string( $category, $term, $country );
		$raw_extensions = wp_remote_get(
			'https://woocommerce.com/wp-json/wccom-extensions/1.0/search' . $parameters
		);
		if ( ! is_wp_error( $raw_extensions ) ) {
			$addons = json_decode( wp_remote_retrieve_body( $raw_extensions ) )->products;
		}
		return $addons;
	}

	/**
	 * Get sections for the addons screen
	 *
	 * @return array of objects
	 */
	public static function get_sections() {
		$addon_sections = get_transient( 'wc_addons_sections' );
		if ( false === ( $addon_sections ) ) {
			$raw_sections = wp_safe_remote_get(
				'https://woocommerce.com/wp-json/wccom-extensions/1.0/categories'
			);
			if ( ! is_wp_error( $raw_sections ) ) {
				$addon_sections = json_decode( wp_remote_retrieve_body( $raw_sections ) );
				if ( $addon_sections ) {
					set_transient( 'wc_addons_sections', $addon_sections, WEEK_IN_SECONDS );
				}
			}
		}
		return apply_filters( 'woocommerce_addons_sections', $addon_sections );
	}

	/**
	 * Get section for the addons screen.
	 *
	 * @param  string $section_id
	 *
	 * @return object|bool
	 */
	public static function get_section( $section_id ) {
		$sections = self::get_sections();
		if ( isset( $sections[ $section_id ] ) ) {
			return $sections[ $section_id ];
		}
		return false;
	}

	/**
	 * Get section content for the addons screen.
	 *
	 * @param  string $section_id
	 *
	 * @return array
	 */
	public static function get_section_data( $section_id ) {
		$section      = self::get_section( $section_id );
		$section_data = '';

		if ( ! empty( $section->endpoint ) ) {
			if ( false === ( $section_data = get_transient( 'wc_addons_section_' . $section_id ) ) ) {
				$raw_section = wp_safe_remote_get( esc_url_raw( $section->endpoint ), array( 'user-agent' => 'WooCommerce Addons Page' ) );

				if ( ! is_wp_error( $raw_section ) ) {
					$section_data = json_decode( wp_remote_retrieve_body( $raw_section ) );

					if ( ! empty( $section_data->products ) ) {
						set_transient( 'wc_addons_section_' . $section_id, $section_data, WEEK_IN_SECONDS );
					}
				}
			}
		}

		return apply_filters( 'woocommerce_addons_section_data', $section_data->products, $section_id );
	}

	/**
	 * Handles the outputting of a contextually aware Storefront link (points to child themes if Storefront is already active).
	 */
	public static function output_storefront_button() {
		$template   = get_option( 'template' );
		$stylesheet = get_option( 'stylesheet' );

		if ( 'storefront' === $template ) {
			if ( 'storefront' === $stylesheet ) {
				$url         = 'https://woocommerce.com/product-category/themes/storefront-child-theme-themes/';
				$text        = __( 'Need a fresh look? Try Storefront child themes', 'woocommerce' );
				$utm_content = 'nostorefrontchildtheme';
			} else {
				$url         = 'https://woocommerce.com/product-category/themes/storefront-child-theme-themes/';
				$text        = __( 'View more Storefront child themes', 'woocommerce' );
				$utm_content = 'hasstorefrontchildtheme';
			}
		} else {
			$url         = 'https://woocommerce.com/storefront/';
			$text        = __( 'Need a theme? Try Storefront', 'woocommerce' );
			$utm_content = 'nostorefront';
		}

		$url = add_query_arg(
			array(
				'utm_source'   => 'addons',
				'utm_medium'   => 'product',
				'utm_campaign' => 'woocommerceplugin',
				'utm_content'  => $utm_content,
			), $url
		);

		echo '<a href="' . esc_url( $url ) . '" class="add-new-h2">' . esc_html( $text ) . '</a>' . "\n";
	}

	/**
	 * Handles the outputting of a banner block.
	 *
	 * @param object $block
	 */
	public static function output_banner_block( $block ) {
		?>
		<div class="addons-banner-block">
			<h1><?php echo esc_html( $block->title ); ?></h1>
			<p><?php echo esc_html( $block->description ); ?></p>
			<div class="addons-banner-block-items">
				<?php foreach ( $block->items as $item ) : ?>
					<?php if ( self::show_extension( $item ) ) : ?>
						<div class="addons-banner-block-item">
							<div class="addons-banner-block-item-icon">
								<img class="addons-img" src="<?php echo esc_url( $item->image ); ?>" />
							</div>
							<div class="addons-banner-block-item-content">
								<h3><?php echo esc_html( $item->title ); ?></h3>
								<p><?php echo esc_html( $item->description ); ?></p>
								<?php
									self::output_button(
										$item->href,
										$item->button,
										'addons-button-solid',
										$item->plugin
									);
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles the outputting of a column.
	 *
	 * @param object $block
	 */
	public static function output_column( $block ) {
		if ( isset( $block->container ) && 'column_container_start' === $block->container ) {
			?>
			<div class="addons-column-section">
			<?php
		}
		if ( 'column_start' === $block->module ) {
			?>
			<div class="addons-column">
			<?php
		} else {
			?>
			</div>
			<?php
		}
		if ( isset( $block->container ) && 'column_container_end' === $block->container ) {
			?>
			</div>
			<?php
		}
	}

	/**
	 * Handles the outputting of a column block.
	 *
	 * @param object $block
	 */
	public static function output_column_block( $block ) {
		?>
		<div class="addons-column-block">
			<h1><?php echo esc_html( $block->title ); ?></h1>
			<p><?php echo esc_html( $block->description ); ?></p>
			<?php foreach ( $block->items as $item ) : ?>
				<?php if ( self::show_extension( $item ) ) : ?>
					<div class="addons-column-block-item">
						<div class="addons-column-block-item-icon">
							<img class="addons-img" src="<?php echo esc_url( $item->image ); ?>" />
						</div>
						<div class="addons-column-block-item-content">
							<h2><?php echo esc_html( $item->title ); ?></h2>
							<?php
								self::output_button(
									$item->href,
									$item->button,
									'addons-button-solid',
									$item->plugin
								);
							?>
							<p><?php echo esc_html( $item->description ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php
	}

	/**
	 * Handles the outputting of a small light block.
	 *
	 * @param object $block
	 */
	public static function output_small_light_block( $block ) {
		?>
		<div class="addons-small-light-block">
			<img class="addons-img" src="<?php echo esc_url( $block->image ); ?>" />
			<div class="addons-small-light-block-content">
				<h1><?php echo esc_html( $block->title ); ?></h1>
				<p><?php echo esc_html( $block->description ); ?></p>
				<div class="addons-small-light-block-buttons">
					<?php foreach ( $block->buttons as $button ) : ?>
						<?php
							self::output_button(
								$button->href,
								$button->text,
								'addons-button-solid'
							);
						?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles the outputting of a small dark block.
	 *
	 * @param object $block
	 */
	public static function output_small_dark_block( $block ) {
		?>
		<div class="addons-small-dark-block">
			<h1><?php echo esc_html( $block->title ); ?></h1>
			<p><?php echo esc_html( $block->description ); ?></p>
			<div class="addons-small-dark-items">
				<?php foreach ( $block->items as $item ) : ?>
					<div class="addons-small-dark-item">
						<?php if ( ! empty( $item->image ) ) : ?>
							<div class="addons-small-dark-item-icon">
								<img class="addons-img" src="<?php echo esc_url( $item->image ); ?>" />
							</div>
						<?php endif; ?>
						<?php
							self::output_button(
								$item->href,
								$item->button,
								'addons-button-outline-white'
							);
						?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles the outputting of featured sections
	 *
	 * @param array $sections
	 */
	public static function output_featured_sections( $sections ) {
		foreach ( $sections as $section ) {
			switch ( $section->module ) {
				case 'banner_block':
					self::output_banner_block( $section );
					break;
				case 'column_start':
					self::output_column( $section );
					break;
				case 'column_end':
					self::output_column( $section );
					break;
				case 'column_block':
					self::output_column_block( $section );
					break;
				case 'small_light_block':
					self::output_small_light_block( $section );
					break;
				case 'small_dark_block':
					self::output_small_dark_block( $section );
					break;
			}
		}
	}

	/**
	 * Outputs a button.
	 *
	 * @param string $url
	 * @param string $text
	 * @param string $theme
	 * @param string $plugin
	 */
	public static function output_button( $url, $text, $theme, $plugin = '' ) {
		$theme = __( 'Free', 'woocommerce' ) === $text ? 'addons-button-outline-green' : $theme;
		$theme = is_plugin_active( $plugin ) ? 'addons-button-installed' : $theme;
		$text  = is_plugin_active( $plugin ) ? __( 'Installed', 'woocommerce' ) : $text;
		?>
		<a
			class="addons-button <?php echo esc_attr( $theme ); ?>"
			href="<?php echo esc_url( $url ); ?>">
			<?php echo esc_html( $text ); ?>
		</a>
		<?php
	}


	/**
	 * Handles output of the addons page in admin.
	 */
	public static function output() {
		if ( isset( $_GET['section'] ) && 'helper' === $_GET['section'] ) {
			do_action( 'woocommerce_helper_output' );
			return;
		}

		$sections        = self::get_sections();
		$theme           = wp_get_theme();
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '_featured';
		$addons          = array();

		if ( '_featured' !== $current_section ) {
			$category = isset( $_GET['section'] ) ? $_GET['section'] : null;
			$term     = isset( $_GET['search'] ) ? $_GET['search'] : null;
			$country  = WC()->countries->get_base_country();
			$addons   = self::get_extension_data( $category, $term, $country );
		}

		/**
		 * Addon page view.
		 *
		 * @uses $addons
		 * @uses $sections
		 * @uses $theme
		 * @uses $current_section
		 */
		include_once dirname( __FILE__ ) . '/views/html-admin-page-addons.php';
	}

	/**
	 * Should an extension be shown on the featured page.
	 *
	 * @param object $item
	 * @return boolean
	 */
	public static function show_extension( $item ) {
		$location = WC()->countries->get_base_country();
		if ( isset( $item->geowhitelist ) && ! in_array( $location, $item->geowhitelist, true ) ) {
			return false;
		}

		if ( isset( $item->geoblacklist ) && in_array( $location, $item->geoblacklist, true ) ) {
			return false;
		}

		if ( is_plugin_active( $item->plugin ) ) {
			return false;
		}

		return true;
	}
}
