# ACF Sync

1. Ensure the WordPress Importer and ACF plugins are installed and active
2. Install and activate this plugin
3. Add a hook in a sub-plugin, mu-plugin, theme or wherever to use the `acf_sync_xml_file_location` filter to specify the location for your XML config file, it is suspected that you will commit this file to remain in sync with your collaborators
4. Use the export/import buttons (everyone loves buttons) in "WordPress Admin Area" > "Custom Fields" > "Sync" to keep in sync
5. Profit

Before including any ACF Fields you've exported to PHP, do a check like:

```php
	if ( ! class_exists( 'ACF_Dev_Mode' ) or ! ACF_Dev_Mode::active() ) {
		// Require the PHP exports from ACF here
	}
```