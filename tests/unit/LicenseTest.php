<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webshr\WpUpdateSdk\Updater;

final class LicenseTest extends TestCase {



    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_update_sdk_http_queue']      = [];
        $GLOBALS['wp_update_sdk_last_request']    = null;
        $GLOBALS['wp_update_sdk_options']         = [];
        $GLOBALS['wp_update_sdk_site_transients'] = [];
    }

    public function test_activate_sends_json_payload_to_activate_endpoint(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'success'       => true,
                    'activation_id' => 'activation-123',
                ],
            ),
        ];

        $updater = new Updater( 'my-plugin', 'https://updates.example.com' );
        $result  = $updater->license()->activate( 'abc123', 'https://example.com' );

        $this->assertIsArray( $result );
        $this->assertSame( 'activation-123', $result['activation_id'] );
        $this->assertSame( 'https://updates.example.com/license/my-plugin/activate', $GLOBALS['wp_update_sdk_last_request']['url'] );
        $this->assertSame( 'POST', $GLOBALS['wp_update_sdk_last_request']['args']['method'] );
        $this->assertSame( '{"license_key":"abc123","site_url":"https:\/\/example.com"}', $GLOBALS['wp_update_sdk_last_request']['args']['body'] );
        $this->assertTrue( $updater->license()->is_active() );
    }

    public function test_set_key_stores_injected_license_without_activation(): void {
        $updater = new Updater( 'my-plugin', 'https://updates.example.com' );

        $updater->license()->set_key( 'abc123', 'https://example.com' );

        $this->assertSame( 'abc123', $updater->license()->license_key() );
        $this->assertFalse( $updater->license()->is_active() );
        $this->assertSame( 'inactive', $updater->state()['status'] );
    }

    public function test_check_builds_query_string_and_returns_error_for_failure(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 500 ],
            'body'     => wp_json_encode(
                [
                    'success' => false,
                    'code'    => 'server_error',
                    'message' => 'Server failed.',
                ],
            ),
        ];

        $updater = new Updater( 'my-plugin', 'https://updates.example.com' );
        $updater->activation()->save(
            [
                'license_key'   => 'abc123',
                'activation_id' => 'activation-123',
                'site_url'      => 'https://example.com',
            ],
        );

        $result = $updater->license()->check();

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'server_error', $result->get_error_code() );
        $this->assertSame( 'https://updates.example.com/license/my-plugin/check?license_key=abc123&activation_id=activation-123&site_url=https%3A%2F%2Fexample.com', $GLOBALS['wp_update_sdk_last_request']['url'] );
        $this->assertSame( 'GET', $GLOBALS['wp_update_sdk_last_request']['args']['method'] );
    }

    public function test_activate_purges_cached_metadata(): void {
        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'update_available' => true,
                    'version'          => '1.2.0',
                    'download_url'     => 'https://downloads.example.com/my-plugin-before-license.zip',
                ],
            ),
        ];

        $updater = new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'project' => 'my-plugin/my-plugin.php',
                'version' => '1.0.0',
            ],
        );

        $updater->check_plugin_update( new \stdClass() );
        $this->assertNotSame( [], $GLOBALS['wp_update_sdk_site_transients'] );

        $GLOBALS['wp_update_sdk_http_queue'][] = [
            'response' => [ 'code' => 200 ],
            'body'     => wp_json_encode(
                [
                    'success'       => true,
                    'activation_id' => 'activation-123',
                ],
            ),
        ];

        $updater->license()->activate( 'abc123', 'https://example.com' );

        $this->assertSame( [], $GLOBALS['wp_update_sdk_site_transients'] );
    }
}
