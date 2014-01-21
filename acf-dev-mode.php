<?php
/*
Copyright © 2013 Code for the People Ltd

*/

if ( ! defined( 'ACF_DEV_MODE' ) )
	define( 'ACF_DEV_MODE', false );

if ( ! defined( 'ACF_LITE' ) )
	define( 'ACF_LITE', ! ACF_DEV_MODE );

class ACF_Dev_Mode {

	const DEV_MODE = ACF_DEV_MODE;

	public function __construct() {

		# Actions:
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
		add_action( 'admin_notices',  array( $this, 'action_admin_notices' ), 1 );

		# Filters:
		# (none)

	}

	public function action_plugins_loaded() {

		if ( ! self::active() ) {
			# Live mode. Only load field groups from PHP exports (ignore the database).
			# Unfortunately the `acf_field_group()` class is instantiated without being assigned
			# to a variable and without being a singleton, so there's no way to un-hook the filter
			# which loads field groups from the database. Instead, we un-hook all hooks and then
			# re-hook the one which loads fields from the PHP export.
			remove_all_filters( 'acf/get_field_groups' );
			add_filter( 'acf/get_field_groups', 'api_acf_get_field_groups', 2 );
		}

	}

	public function action_admin_notices() {
		if ( self::active() ) {
			?>
			<div id="acf_dev_notice" class="error">
				<p>
					<?php printf( __( 'ACF development mode is on. ACF fields are being loaded from the database, not the PHP export. Remember to <a href="%1$s">export to PHP</a> and <a href="%2$s">sync the XML</a> when you’re done!', 'acf-sync' ), admin_url( 'edit.php?post_type=acf&page=acf-export' ), admin_url( 'edit.php?post_type=acf&page=acf_sync' ) ); ?>
				</p>
			</div>
			<?php
		}
	}

	public static function active() {
		return self::DEV_MODE;
	}

	public static function init( $file = null ) {

		static $instance = null;

		if ( ! $instance )
			$instance = new ACF_Dev_Mode( $file );

		return $instance;

	}



}

ACF_Dev_Mode::init( __FILE__ );
