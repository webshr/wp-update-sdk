<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webshr\WpUpdateSdk\Updater;

final class UpdaterFacadeTest extends TestCase {


    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_update_sdk_options'] = [];
    }

    public function test_option_prefix_can_be_overwritten_after_construction(): void {
        $updater = new Updater(
            'my-plugin',
            'https://updates.example.com',
            [
                'option_prefix' => 'initial_prefix',
            ],
        );

        $this->assertSame( 'initial_prefix', $updater->option_prefix() );
        $this->assertSame( 'initial_prefix_my-plugin', $updater->option_name() );

        $updater->set_option_prefix( 'custom_prefix' );

        $this->assertSame( 'custom_prefix', $updater->option_prefix() );
        $this->assertSame( 'custom_prefix_my-plugin', $updater->option_name() );
    }

    public function test_metadata_url_only_includes_license_values_after_activation(): void {
        $updater = new Updater( 'my-plugin', 'https://updates.example.com' );

        $this->assertSame(
            'https://updates.example.com/metadata/my-plugin?channel=stable',
            $updater->metadata_url(),
        );

        $updater->activation()->save(
            [
                'license_key'   => 'abc123',
                'activation_id' => 'activation-123',
                'site_url'      => 'https://example.com',
                'channel'       => 'beta',
            ],
        );

        $this->assertSame(
            'https://updates.example.com/metadata/my-plugin?channel=beta&license_key=abc123&activation_id=activation-123&site_url=https%3A%2F%2Fexample.com',
            $updater->metadata_url(),
        );
    }
}
