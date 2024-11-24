<?php

/**
 * Plugin Name: WP-sprinkles
 * Plugin URI: https://github.com/karmatosed/WP-Sprinkles/
 * Description: WP-sprinkles
 * Version: 1.0
 * Author: Anyone that wants to join
 * Text Domain: WP-Sprinkles
 */

class WPSprinkles{

	/**
	 * List of all CSS files
	 */
	private $WP_Sprinkles_css_files;

	private $meta_data = array();

	function __construct() {

		// Generate a list of all CSS files.
		$this->WP_Sprinkles_css_files = glob( plugin_dir_path( __FILE__ ) . 'css/*.css' );

		$this->get_WP_Sprinkles_meta_data();

		// Add admin actions.
		add_action( 'admin_menu', array( $this, 'WP_Sprinkles_add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'WP_Sprinkles_settings' ) );
		add_action( 'admin_notices', array( $this, 'WP_Sprinkles_admin_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'WP_Sprinkles_enqueue_stylesheets' ), 100 );

		// Filters.
		add_filter( 'plugin_action_links_WP-Sprinkles/index.php', array( $this, 'WP_Sprinkles_add_settings_link' ) );
	}

	/**
	* Gets the meta data from each design experiment.
	* return array $meta_data each experiment with its meta data.
	*/
	private function get_WP_Sprinkles_meta_data() {
		$file_headers = array(
			'title'   		=> 'Title',
			'description' 	=> 'Description',
			'pr'     		=> 'PR',
		);
		
		foreach ( $this->WP_Sprinkles_css_files as $file ) {
			$name = basename( $file, '.css' );
			$this->meta_data[ $name ] = get_file_data( $file, $file_headers );
		}
	}

	/**
	 * Set up a WP-Admin page for managing turning on and off plugin features.
	 */
	function WP_Sprinkles_add_settings_page() {
		add_options_page( 'WP-sprinkles', 'WP-sprinkles', 'manage_options', 'WP-Sprinkles', array( $this, 'WP_Sprinkles_settings_page' ) );
	}

	/**
	 * Register settings for the WP-Admin page.
	 */
	function WP_Sprinkles_settings() {
		$design_setting_args = array(
			'type'    => 'string',
			'default' => 'default',
		);
		register_setting( 'WP-Sprinkles-settings', 'WP-Sprinkles-setting', $design_setting_args );
	}


	/**
	 * Fetch experiment title from the CSS file.
	 */
	private function get_title( $experiment_name ) {

		if ( array_key_exists( $experiment_name, $this->meta_data ) && ! empty( $this->meta_data[ $experiment_name ]['title'] ) ) {
			$title = $this->meta_data[ $experiment_name ]['title'];
		} else {
			$title = ucfirst( str_replace( '-', ' ', $experiment_name ) );
		}

		return esc_html( $title );
	}


	/**
	 * Fetch experiment metadata from the CSS file.
	 */
	private function output_meta_data ( $experiment_name ) {

		if ( ! array_key_exists( $experiment_name, $this->meta_data ) ) {
			return false;
		}

		$experiment_meta = $this->meta_data[ $experiment_name ];

		if ( ! empty( $experiment_meta['description'] ) ) {
			?>
			<p><?php echo esc_html( $experiment_meta['description'] ); ?></p>
			<?php
		}

		if ( ! empty( $experiment_meta['pr'] ) ) {
			?>
			<p>
				<a href="<?php echo esc_url( $experiment_meta['pr'] ); ?>"><?php _e( 'Details', 'WP-Sprinkles' ); ?></a>
			</p>
			<?php
		}
	}


	/**
	 * Build the WP-Admin settings page.
	 */
	function WP_Sprinkles_settings_page() { ?>

		<div class="wrap">
		<h1><?php _e( 'WP-sprinkles', 'WP-Sprinkles' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'WP-Sprinkles-settings' ); ?>
			<?php do_settings_sections( 'WP-Sprinkles-settings' ); ?>

			<table class="form-table" style="width: auto;">
			<?php

			foreach ( $this->WP_Sprinkles_css_files as $key => $css_file ) {
				$experiment_name = basename( $css_file, '.css' );
				$experiment_title = $this->get_title( $experiment_name );
				$id = esc_attr( "WP-Sprinkles-setting-{$key}" );

				?>
				<tr>
					<td style="vertical-align: top;">
						<input
							type="radio"
							id="<?php echo $id; ?>"
							name="WP-Sprinkles-setting"
							value="<?php echo esc_attr( $experiment_name ); ?>"
							<?php checked( $experiment_name, get_option( 'WP-Sprinkles-setting' ) ); ?>
						/>
					</td>
					<td>
						<label for="<?php echo $id; ?>" style="font-weight: bold">
							<?php echo esc_html( $experiment_title ); ?>
						</label>
						<?php $this->output_meta_data( $experiment_name ); ?>
					</td>
				</tr>
				<?php
			}

			?>
			</table>

			<?php submit_button(); ?>
		</form>
		</div>
	<?php }


	/**
	 * Enqueue Stylesheets.
	 */
	function WP_Sprinkles_enqueue_stylesheets() {

		$option = get_option( 'WP-Sprinkles-setting' );

		foreach ( $this->WP_Sprinkles_css_files as $css_file ) {
			$experiment_name = basename( $css_file, '.css' );

			if ( $option === $experiment_name ) {
				$experiment_url = plugins_url( 'css/' . basename( $css_file ), __FILE__ );

				// Auto-bust stylesheet cache.
				$mtime = @filemtime( $css_file );
				$version = $mtime ? $mtime : time();

				wp_register_style( $experiment_name , $experiment_url, false, $version );
				wp_enqueue_style( $experiment_name );
				break;
			}
		}
	}

	/**
	 * Display a warning on the plugin page.
	 */
	function WP_Sprinkles_admin_notice() {
		$screen = get_current_screen();

		if ( $screen->id === 'settings_page_WP-Sprinkles' ) { 
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php _e( 'Warning:', 'WP-Sprinkles' ); ?> </strong>
					<?php _e( 'These experiments may hide or visually break functionality in the admin area. This plugin is for testing concepts, and is not intended for use on a production site.', 'WP-Sprinkles' ) ?>
				</p>
			</div>
			<?php 
		}
	}

	/**
	 * Include a link to the plugin settings on the main plugins page.
	 */
	function WP_Sprinkles_add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=WP-Sprinkles">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

}

new WPSprinkles;
