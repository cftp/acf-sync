<?php 

/*
Plugin Name: Advanced Custom Fields: Sync
Plugin URI: https://github.com/cftp/acf-sync
Description: Sync ACF configs more easily, also adds an "ACF_DEV_MODE" constant to switch between the fields in the DB and those in code.
Version: 0.1
Author: Code for the People Ltd
Author URI: http://codeforthepeople.com/
*/
 
/*  Copyright 2013 Code for the People Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

if ( ! defined( 'ACF_DEV_MODE' ) )
	define( 'ACF_DEV_MODE', false );

if ( ! defined( 'ACF_LITE' ) )
	define( 'ACF_LITE', ! ACF_DEV_MODE );

/**
 * 
 * 
 * @package ACF Sync
 **/
class ACF_Sync {

	const DEV_MODE = ACF_DEV_MODE;

	/**
	 * A version integer.
	 *
	 * @var int
	 **/
	var $version;

	/**
	 * Singleton stuff.
	 * 
	 * @access @static
	 * 
	 * @return ACF_Sync object
	 */
	static public function init() {
		static $instance = false;

		if ( ! $instance )
			$instance = new ACF_Sync;

		return $instance;

	}

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct() {
		add_action( 'init',          array( $this, 'action_init' ) );
		add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );

		$this->version = 1;
	}

	// HOOKS
	// =====

	/**
	 * Hooks the WP action init
	 *
	 * @action init
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_init() {
		// Sanity checks
		if ( ! is_admin() )
			return;
		if ( ! $this->is_wp_importer_loaded() )
			return;
		if ( ! $this->is_acf_loaded() )
			return;

		add_action( 'admin_menu',                       array( $this, 'action_admin_menu' ) );
		add_action( 'load-custom-fields_page_acf_sync', array( $this, 'action_load_page_acf_sync' ) );
		add_action( 'plugins_loaded',                   array( $this, 'action_plugins_loaded' ) );
	}

	/**
	 * Hooks the WP action admin_notices
	 *
	 * @action admin_notices
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_admin_notices() {
		if ( ! $this->is_wp_importer_loaded() )
			$this->admin_notice_error( sprintf( __( 'Please install the <a href="%s" target="_blank">WordPress Importer plugin</a>, as the ACF Sync plugin requires it.', 'acf-sync' ), 'http://wordpress.org/plugins/wordpress-importer/' ) );
			
		if ( ! $this->is_acf_loaded() )
			$this->admin_notice_error( sprintf( __( 'Please install the <a href="%s" target="_blank">Advanced Custom Fields plugin</a>, as the ACF Sync plugin requires it.', 'acf-sync' ), 'http://wordpress.org/plugins/advanced-custom-fields/' ) );

		if ( self::is_dev_mode_active() )
			$this->admin_notice_error( sprintf( __( 'ACF development mode is on. ACF fields are being loaded from the database, not the PHP export. Remember to <a href="%s">export when you’re done</a>!', 'acf-sync' ), admin_url( 'edit.php?post_type=acf&page=acf-export' ) ) );

		$this->show_new_old_message();
	}

	/**
	 * Hooks the WP action admin_menu
	 *
	 * @action admin_menu
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_admin_menu() {
		add_submenu_page( 'edit.php?post_type=acf', __( 'Sync', 'acf-sync' ), __( 'Sync', 'acf-sync' ), 'manage_options', 'acf_sync', array( $this, 'callback_acf_sync_page' ) );
	}

	/**
	 * Hooks the WP action load_page_acf_sync
	 *
	 * @action load-custom-fields_page_acf_sync
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_load_page_acf_sync() {
		if ( ! isset( $_POST[ '_acf_sync_nonce' ] ) )
			return;

		check_admin_referer( 'acf_sync_load', '_acf_sync_nonce' );

		if ( isset( $_POST[ 'acf_sync_export_to_file' ] ) )
			$this->export_to_file();

		if ( isset( $_POST[ 'acf_sync_import_from_file' ] ) )
			$this->import_from_file();
	}

	/**
	 * 
	 *
	 * @author John Blackbourn
	 **/
	public function action_plugins_loaded() {

		if ( ! self::is_dev_mode_active() ) {
			# Live mode. Only load field groups from PHP exports (ignore the database).
			# Unfortunately the `acf_field_group()` class is instantiated without being assigned
			# to a variable and without being a singleton, so there's no way to un-hook the filter
			# which loads field groups from the database. Instead, we un-hook all hooks and then
			# re-hook the one which loads fields from the PHP export.
			remove_all_filters( 'acf/get_field_groups' );
			add_filter( 'acf/get_field_groups', 'api_acf_get_field_groups', 2 );
		}

	}

	// CALLBACKS
	// =========

	/**
	 * Provides HTML for the ACF Sync admin page
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function callback_acf_sync_page() {
		// @TODO: Show a notice when the XML file has changed since the last import somehow, and prompt the user to re-import.
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e( 'Sync', 'acf-sync' ); ?></h2>

			<p>
				<?php printf( __( 'The XML file will be stored at <var>%s</var>, you can control this with the <var>acf_sync_xml_file_location</var> filter.', 'acf-sync' ), $this->xml_file_location() ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( add_query_arg( array( 'import' => 1 ) ) ); ?>">
				<?php wp_nonce_field( 'export', 'nonce' ); ?>
				<?php wp_nonce_field( 'acf_sync_load', '_acf_sync_nonce' ); ?>

				<?php 
					$acf_query = $this->get_acf_query();
					foreach ( $acf_query->posts as $post_id ) {
						?><input type="hidden" name="acf_posts[]" value="<?php echo absint( $post_id ); ?>" /><?php
					}
				?>

				<p>
					<?php submit_button( __( 'Export File' ), 'primary', 'acf_sync_export_to_file', false ); ?>
					<?php submit_button( __( 'Import File' ), 'primary', 'acf_sync_import_from_file', false ); ?>
				</p>

			</form>
		</div>
		<?php
	}

	// UTILITIES
	// =========

	/**
	 * Exports the XML file to the nominated location
	 * in the filesystem.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function export_to_file() {
		// @TODO Make sure the file location is writable, and inform the user if not
		// Start buffering output
		ob_start();
		// Include ACF core export file 
		$path = apply_filters('acf/get_info', 'path');
		include_once($path . 'core/actions/export.php');
		// Now we have to remove the headers. Grrr.
		header_remove( 'Content-Description' );
		header_remove( 'Content-Disposition' );
		header_remove( 'Content-Type' );
		// Capture and delete output buffeer
		$xml = ob_get_clean();
		// // Write captured output buffer to a file
		$xml_file_location = $this->xml_file_location();
		file_put_contents( $xml_file_location, $xml );
		// Redirect and show notice
		$redirect_to = admin_url( 'edit.php' );
		// @TODO: Show message to tell the user we've exported, what was exported and when (so something changes when they do it again)
		$redirect_to = add_query_arg( array( 'post_type' => 'acf', 'page' => 'acf_sync', 'acf_sync_msg_1' => 1 ), $redirect_to );
		wp_safe_redirect( $redirect_to );
	}

	/**
	 * Imports the XML file at the nominated location
	 * in the filesystem.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function import_from_file() {
		$file = $this->xml_file_location();

		// Delete all ACF posts before importing, so we get updates
		$acf_query = $this->get_acf_query();
		foreach ( $acf_query->posts as $post_id )
			wp_delete_post( $post_id, true );

		// Output buffering so we can discard the message from the WP Importer
		ob_start();

		set_time_limit(0);
		// @TODO: Make sure WP Importer plugin is present
		$wp_import = new WP_Import();
		$res = $wp_import->import( $file );

		ob_end_clean();

		// Redirect and show notice
		$redirect_to = admin_url( 'edit.php' );
		// @TODO: Show message to tell the user we've exported, what was exported and when (so something changes when they do it again)
		$redirect_to = add_query_arg( array( 'post_type' => 'acf', 'page' => 'acf_sync', 'acf_sync_msg_2' => 1 ), $redirect_to );
		wp_safe_redirect( $redirect_to );
	}

	/**
	 * Provides the location to store 
	 * the XML file at.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function xml_file_location() {
		if ( is_multisite() )
			$filename = sprintf( 'acf-config-site-%d.xml', $GLOBALS[ 'blog_id' ] );
		else
			$filename = 'acf-config-site.xml';
		$filepath = ABSPATH . $filename;
		return apply_filters( 'acf_sync_xml_file_location', $filepath, $filename );
	}

	/**
	 * Returns a WP_Query to get all the ACF post IDs.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function get_acf_query() {
		return new WP_Query( array(
			'post_type'   => 'acf',
			'post_status' => 'any',
			'fields'      => 'ids',
		) );
	}

	/**
	 * 
	 *
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function show_new_old_message() {
		// @FIXME: How can this be more useful?
		$xml_file = $this->xml_file_location();
		if ( is_file( $xml_file ) )
			$xml_file_mod_time = filemtime( $xml_file );
		else
			return;
		$newest_acf_query = new WP_Query( array(
			'post_type'      => 'acf',
			'post_status'    => 'any',
			'order'          => 'DESC',
			'orderby'        => 'modified',
			'posts_per_page' => 1,
		) );
		if ( $newest_acf_query->have_posts() ) {
			$latest_mod_time = mysql2date( 'U', $newest_acf_query->posts[ 0 ]->post_modified );
			if ( $latest_mod_time > $xml_file_mod_time )
				$this->admin_notice_msg( sprintf( __( 'ACF Sync file is %s older than the youngest ACF modification. Do you need to export?', 'acf-sync' ), human_time_diff( $latest_mod_time, $xml_file_mod_time ) ) );
			else
				$this->admin_notice_msg( sprintf( __( 'ACF Sync file is %s younger than the youngest ACF modification. Do you need to import?', 'acf-sync' ), human_time_diff( $xml_file_mod_time, $latest_mod_time ) ) );
		} else {
			$this->admin_notice_msg( sprintf( __( 'ACF Sync file is %s old.', 'acf-sync' ), human_time_diff( $xml_file_mod_time ) ) );	
		}
	}

	/**
	 * Checks ACF plugin is active.
	 *
	 * @return bool True if ACF plugin is active
	 * @author Simon Wheatley
	 **/
	public function is_acf_loaded() {
		return class_exists( 'Acf' );
	}

	/**
	 * Checks WP Importer plugin is active.
	 *
	 * @return bool True if WP Importer plugin is active
	 * @author Simon Wheatley
	 **/
	public function is_wp_importer_loaded() {
		// The WP Importer is near invisible when not importing,
		// so we cannot test for classes, functions, constants, etc.
		// Gather the active plugin paths.
		$plugins = array_merge( wp_get_mu_plugins(), wp_get_active_and_valid_plugins() );
		if ( function_exists( 'wp_get_active_network_plugins' ) )
			$plugins = array_merge( $plugins, wp_get_active_network_plugins() );
		// Check each plugin path to see it it ends in `wordpress-importer.php`
		foreach ( $plugins as $plugin_path ) {
			if ( preg_match( '/wordpress-importer\.php$/i', $plugin_path ) )
				return true;
		}
		return false;
	}

		/**
	 * 
	 *
	 * @author John Blackbourn
	 **/
	public static function is_dev_mode_active() {
		return self::DEV_MODE;
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string URL
	 * @author John Blackbourn
	 **/
	protected function plugin_url( $file = '' ) {
		return $this->plugin( 'url', $file );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Filesystem path
	 * @author John Blackbourn
	 **/
	protected function plugin_path( $file = '' ) {
		return $this->plugin( 'path', $file );
	}

	/**
	 * Returns a version number for the given plugin file.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Version
	 * @author John Blackbourn
	 **/
	protected function plugin_ver( $file ) {
		return filemtime( $this->plugin_path( $file ) );
	}

	/**
	 * Returns the current plugin's basename, eg. 'my_plugin/my_plugin.php'.
	 *
	 * @return string Basename
	 * @author John Blackbourn
	 **/
	protected function plugin_base() {
		return $this->plugin( 'base' );
	}

	/**
	 * Populates and returns the current plugin info.
	 *
	 * @author John Blackbourn
	 **/
	protected function plugin( $item, $file = '' ) {
		if ( ! isset( $this->plugin ) ) {
			$this->plugin = array(
				'url'  => plugin_dir_url( $this->file ),
				'path' => plugin_dir_path( $this->file ),
				'base' => plugin_basename( $this->file )
			);
		}
		return $this->plugin[ $item ] . ltrim( $file, '/' );
	}

	/**
	 * Output the HTML for an admin notice area error.
	 *
	 * @param sting $msg The error message to show
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function admin_notice_error( $msg ) {
		$allowed_html = array(
			'address' => array(),
			'a' => array(
				'href' => true,
				'name' => true,
				'target' => true,
			),
			'em' => array(),
			'strong' => array(),
		);
		?>
		<div class="fade error">
			<p><?php echo wp_kses( $msg, $allowed_html ); ?></p>
		</div>
		<?php
	}

	/**
	 * Output the HTML for an admin notice area msg.
	 *
	 * @param sting $msg The error message to show
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function admin_notice_msg( $msg ) {
		$allowed_html = array(
			'address' => array(),
			'a' => array(
				'href' => true,
				'name' => true,
				'target' => true,
			),
			'em' => array(),
			'strong' => array(),
		);
		?>
		<div class="updated">
			<p><?php echo wp_kses( $msg, $allowed_html ); ?></p>
		</div>
		<?php
	}

}


// Initiate the singleton
ACF_Sync::init();





