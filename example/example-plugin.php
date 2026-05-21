<?php
/**
 * Plugin Name: Example Plugin (WP Update SDK)
 * Version: 1.0.0
 * Update URI: https://updates.example.com/metadata/example-plugin
 */

use Webshr\WpUpdateSdk\Updater;

require_once __DIR__ . '/../vendor/autoload.php';

$wpus_example_plugin_header = get_file_data( __FILE__, [ 'Version' => 'Version' ] );
define( 'WPUS_EXAMPLE_PLUGIN_VERSION', $wpus_example_plugin_header['Version'] );
define( 'WPUS_EXAMPLE_PLUGIN_LICENSE_KEY', 'your-license-key-here' );

$wpus_example_plugin_updater = new Updater(
    'example-plugin',
    'https://updates.example.com',
    [
        'project' => 'example-plugin/example-plugin.php',
        'version' => WPUS_EXAMPLE_PLUGIN_VERSION,
        'type'    => 'plugin',
    ],
);

// Example: inject a license key from a constant. Activation can happen through the settings page.
$wpus_example_plugin_updater->license()->set_key( WPUS_EXAMPLE_PLUGIN_LICENSE_KEY );

// Add settings page under Settings > Example Plugin Updates.
$wpus_example_plugin_updater->settings()->add_page(
    [
        'type'        => 'submenu',
        'parent_slug' => 'options-general.php',
        'page_title'  => 'Example Plugin Updates',
        'menu_title'  => 'Example Plugin Updates',
        'menu_slug'   => 'example-plugin-updates',
    ],
);

$wpus_example_plugin_updater->run();

// Example: expose a shortcode that shows optional license status.
add_shortcode(
    'example_license_status',
    function () use ( $wpus_example_plugin_updater ) {
        if ( $wpus_example_plugin_updater->license()->is_active() ) {
            return '<p>License active - expires: ' . esc_html( (string) ( $wpus_example_plugin_updater->state()['expires_at'] ?? 'unknown' ) ) . '</p>';
        }

        return '<p>No active license.</p>';
    },
);
