# ACF Sync

1. Ensure the WordPress Importer and ACF plugins are installed and active
2. Install this plugin **as a `mu-plugin`**
3. Add a hook in a sub-plugin, mu-plugin, theme or wherever to use the `acf_sync_xml_file_location` filter to specify the location for your XML config file, it is expected that you will commit this file to remain in sync with your collaborators
4. Use the export/import buttons (everyone loves buttons) in "WordPress Admin Area" > "Custom Fields" > "Sync" to keep in sync
5. Profit

Before including any ACF Fields you've exported to PHP, do a check like:

```php
	if ( ! class_exists( 'ACF_Dev_Mode' ) or ! ACF_Dev_Mode::active() ) {
		// Require the PHP exports from ACF here
	}
```

Whenever you need to change an ACF field:

1. Enable dev mode by adding `define( 'ACF_DEV_MODE', true );` to your `local-config.php`.
1. Make sure your local copy of the theme is up to date. Import fields using ACF sync.
1. Then makes your amends, do a PHP export to acf_fields.php in the Keystone Structure plugin and commit.
1. Then do an ACF sync and commit *and push* the changes to the `.xml` file in the theme.
1. Disable dev mode.

Note that this plugin needs to be present on development and production, otherwise if you have fields defined in both DB (through the WP admin area) and in the PHP exports, you will see double sets of fields.
