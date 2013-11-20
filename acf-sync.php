<?php 

/*
Plugin Name: Advanced Custom Fields: Sync
Plugin URI: http://codeforthepeople.com/?plugin=acf_sync
Description: Description
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


/**
 * 
 * 
 * @package ACF Sync
 **/
class ACF_Sync {

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
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		$this->version = 1;
	}

	// HOOKS
	// =====

	/**
	 * Hooks the WP action admin_init
	 *
	 * @action admin_init
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_admin_init() {
		$this->maybe_upgrade();
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

	// CALLBACKS
	// =========

	/**
	 * Provides HTML for the ACF Sync admin page
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function callback_acf_sync_page() {
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php _e( 'Sync', 'acf-sync' ); ?></h2>

			<p>
				This screen enables you to export to a file and import from it, the 
				file will be stored at <var>xxxx</var>. This enables one to more easily
				work with ACF as a team and to store ACF configs in version control.
			</p>

			<form method="post" action="">

				<p>
					<?php submit_button( __( 'Export File' ), 'primary', 'acfsync_export_to_file', false ); ?>
					<?php submit_button( __( 'Import File' ), 'primary', 'acfsync_import_from_file', false ); ?>
				</p>

			</form>
		</div>
		<?php
	}

	// UTILITIES
	// =========

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
	 * Checks the DB structure is up to date, rewrite rules, 
	 * theme image size options are set, etc.
	 *
	 * @return void
	 **/
	public function maybe_upgrade() {
		global $wpdb;
		$option_name = 'acf_sync_version';
		$version = get_option( $option_name, 0 );

		if ( $version == $this->version )
			return;

		// if ( $version < 1 ) {
		// 	error_log( "ACF Sync: …" );
		// }

		// N.B. Remember to increment $this->version above when you add a new IF

		update_option( $option_name, $this->version );
		error_log( "ACF Sync: Done upgrade, now at version " . $this->version );
	}
}


// Initiate the singleton
ACF_Sync::init();





