<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webshr\WpUpdateSdk\Updater;

final class UpdaterTest extends TestCase {



    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_update_sdk_http_queue']      = [];
        $GLOBALS['wp_update_sdk_site_transients'] = [];
        $GLOBALS['wp_update_sdk_last_request']    = null;
        $GLOBALS['wp_update_sdk_is_multisite']    = false;
        $GLOBALS['wp_update_sdk_hooks']           = [
            'filters' => [],
            'actions' => [],
        ];
    }

    public function test_plugin_update_check_adds_response_and_uses_cached_metadata(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'update_available' => true,
                    'version'          => '1.2.0',
                    'download_url'     => 'https://downloads.example.com/my-plugin.zip',
                    'homepage'         => 'https://example.com/my-plugin',
                    'tested'           => '6.5',
                    'requires_php'     => '8.1',
                ],
            ),
        ];

        $updater = new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'project' => 'my-plugin/my-plugin.php',
                'version' => '1.0.0',
                'type'    => 'plugin',
            ],
        );
        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['filters'] ?? [] );
        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['actions'] ?? [] );

        $updater->run();
        $this->assertArrayHasKey( 'pre_set_site_transient_update_plugins', $GLOBALS['wp_update_sdk_hooks']['filters'] );
        $this->assertArrayHasKey( 'upgrader_package_options', $GLOBALS['wp_update_sdk_hooks']['filters'] );

        $transient = $updater->check_plugin_update( new \stdClass() );

        $this->assertObjectHasProperty( 'response', $transient );
        $this->assertArrayHasKey( 'my-plugin/my-plugin.php', $transient->response );
        $this->assertSame( '1.2.0', $transient->response['my-plugin/my-plugin.php']->new_version );
        $this->assertSame( 'my-plugin/my-plugin.php', $transient->response['my-plugin/my-plugin.php']->plugin );
        $this->assertStringStartsWith( 'https://updates.example.com/metadata/my-plugin', $GLOBALS['wp_update_sdk_last_request']['url'] );

        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'update_available' => true,
                    'version'          => '1.3.0',
                    'download_url'     => 'https://downloads.example.com/my-plugin-1.3.0.zip',
                ],
            ),
        ];

        $cachedTransient = $updater->check_plugin_update( new \stdClass() );
        $this->assertSame( '1.2.0', $cachedTransient->response['my-plugin/my-plugin.php']->new_version );
    }

    public function test_refresh_package_options_replaces_package_with_fresh_download_url(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'update_available' => true,
                    'version'          => '1.2.0',
                    'download_url'     => 'https://downloads.example.com/my-plugin-fresh.zip',
                ],
            ),
        ];

        $updater = new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'project' => 'my-plugin/my-plugin.php',
                'version' => '1.0.0',
                'type'    => 'plugin',
            ],
        );

        $options = $updater->refresh_package_options(
            [
                'package'    => 'https://downloads.example.com/my-plugin-expired.zip',
                'hook_extra' => [
                    'action' => 'update',
                    'type'   => 'plugin',
                    'plugin' => 'my-plugin/my-plugin.php',
                ],
            ],
        );

        $this->assertSame( 'https://downloads.example.com/my-plugin-fresh.zip', $options['package'] );
        $this->assertStringStartsWith( 'https://updates.example.com/metadata/my-plugin', $GLOBALS['wp_update_sdk_last_request']['url'] );
    }

    public function test_refresh_package_options_ignores_other_projects(): void {
        $updater = new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'project' => 'my-plugin/my-plugin.php',
                'version' => '1.0.0',
                'type'    => 'plugin',
            ],
        );

        $options = $updater->refresh_package_options(
            [
                'package'    => 'https://downloads.example.com/other-plugin.zip',
                'hook_extra' => [
                    'action' => 'update',
                    'type'   => 'plugin',
                    'plugin' => 'other-plugin/other-plugin.php',
                ],
            ],
        );

        $this->assertSame( 'https://downloads.example.com/other-plugin.zip', $options['package'] );
        $this->assertNull( $GLOBALS['wp_update_sdk_last_request'] );
    }

    public function test_theme_update_check_adds_response(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'update_available' => true,
                    'version'          => '2.0.0',
                    'download_url'     => 'https://downloads.example.com/my-theme.zip',
                    'homepage'         => 'https://example.com/my-theme',
                ],
            ),
        ];

        $updater = new Updater(
            'my-theme',
            'https://updates.example.com',
            [
                'project' => 'my-theme',
                'version' => '1.0.0',
                'type'    => 'theme',
            ],
        );
        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['filters'] ?? [] );
        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['actions'] ?? [] );

        $updater->run();
        $this->assertArrayHasKey( 'pre_set_site_transient_update_themes', $GLOBALS['wp_update_sdk_hooks']['filters'] );
        $this->assertArrayHasKey( 'upgrader_package_options', $GLOBALS['wp_update_sdk_hooks']['filters'] );

        $transient = $updater->check_theme_update( new \stdClass() );

        $this->assertObjectHasProperty( 'response', $transient );
        $this->assertArrayHasKey( 'my-theme', $transient->response );
        $this->assertSame( '2.0.0', $transient->response['my-theme']['new_version'] );
        $this->assertSame( 'my-theme', $transient->response['my-theme']['theme'] );
    }

    public function test_constructor_does_not_register_hooks(): void {
        new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'project' => 'my-plugin/my-plugin.php',
                'version' => '1.0.0',
                'type'    => 'plugin',
            ],
        );

        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['filters'] ?? [] );
        $this->assertSame( [], $GLOBALS['wp_update_sdk_hooks']['actions'] ?? [] );
    }
}
