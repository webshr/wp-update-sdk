# Webshr WordPress Update SDK

Composer-first WordPress update client for the Webshr update server.

It registers plugin/theme updates, stores release channel preferences, provides an optional settings page, and supports optional license activation when your update server requires it.

## Install

```bash
composer require webshr/wp-update-sdk
```

Use the updater through Composer autoloading:

```php
use Webshr\WpUpdateSdk\Updater;
```

## Plugin Usage

```php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Update URI: https://updates.example.com/metadata/my-plugin
 */

use Webshr\WpUpdateSdk\Updater;

require __DIR__ . '/vendor/autoload.php';

$updater = new Updater(
    'my-plugin',
    'https://updates.example.com',
    [
        'project' => 'my-plugin/my-plugin.php',
        'version' => '1.0.0',
        'type'    => 'plugin',
    ],
);

$updater->settings()->add_page( [
    'type'        => 'submenu',
    'parent_slug' => 'options-general.php',
    'page_title'  => 'My Plugin Updates',
    'menu_title'  => 'My Plugin Updates',
    'menu_slug'   => 'my-plugin-updates',
] );

$updater->run();
```

### Constructor Arguments

```php
$updater = new Updater(
    'my-plugin',
    'https://updates.example.com',
    [
        'project'       => 'my-plugin/my-plugin.php', // Plugin file or theme stylesheet.
        'version'       => '1.0.0',
        'type'          => 'plugin', // plugin or theme.
        'option_prefix' => 'wp_update_sdk', // Optional.
    ],
);
```

## Theme Usage

```php
use Webshr\WpUpdateSdk\Updater;

require get_template_directory() . '/vendor/autoload.php';

$theme = wp_get_theme();

$updater = new Updater(
    get_stylesheet(),
    'https://updates.example.com',
    [
        'project' => get_stylesheet(),
        'version' => $theme->get( 'Version' ),
        'type'    => 'theme',
    ],
);

$updater->settings()->add_page( [
    'type'        => 'submenu',
    'parent_slug' => 'themes.php',
    'page_title'  => 'Theme Updates',
    'menu_title'  => 'Theme Updates',
    'menu_slug'   => get_stylesheet() . '-updates',
] );

$updater->run();
```

## Optional Licensing

Updates do not require a license unless your update server requires one for the package. When you need licensing, use the updater's license module:

```php
$result = $updater->license()->activate( 'CUSTOMER-LICENSE-KEY' );

if ( is_wp_error( $result ) ) {
    wp_die( esc_html( $result->get_error_message() ) );
}
```

For license keys injected from constants, set the key during plugin boot. This stores the key for metadata requests and pre-fills the settings field, but it does not mark the license active until activation succeeds:

```php
if ( defined( 'MY_PLUGIN_LICENSE_KEY' ) && '' !== MY_PLUGIN_LICENSE_KEY ) {
    $updater->license()->set_key( MY_PLUGIN_LICENSE_KEY );
}
```

You can also check or deactivate a stored license:

```php
$updater->license()->check();
$updater->license()->deactivate();
```

## Settings Page

The SDK page includes:

- release channel selector: `stable`, `beta`, `alpha`, `rc`
- optional license key input
- save settings button
- activate/update license button
- check license button
- deactivate button

By default the page is added under Settings. You can also attach it to a top-level or submenu page:

```php
$updater->settings()->add_page( [
    'type' => 'menu', // menu, submenu, or options
    'page_title' => 'Updates',
    'menu_title' => 'Updates',
    'capability' => 'manage_options',
    'menu_slug' => 'my-product-updates',
] );
```

For a submenu under an existing custom admin menu, use the parent menu slug:

```php
$updater->settings()->add_page( [
    'type'        => 'submenu',
    'parent_slug' => 'my-plugin',
    'page_title'  => 'Updates',
    'menu_title'  => 'Updates',
    'menu_slug'   => 'my-plugin-updates',
] );
```

## Channels

The release channel is stored locally and always sent to the metadata endpoint:

```php
$updater->set_channel( 'beta' );
echo $updater->metadata_url();
```

Without an activated license, metadata requests include only the channel:

```text
channel
```

After a license is activated, metadata requests also include:

```text
license_key
activation_id
site_url
```

## Package Structure

```text
src/Activation.php  WordPress option-backed update/license state
src/Client.php      HTTP transport
src/License.php     Optional license lifecycle
src/Settings.php    Optional update settings screen
src/Updater.php     Public SDK facade and WordPress update integration
composer.json       Package metadata and autoload rules
```
