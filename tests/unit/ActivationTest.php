<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webshr\WpUpdateSdk\Activation;

final class ActivationTest extends TestCase {



    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['wp_update_sdk_options'] = [];
    }

    public function test_option_name_is_returned_as_configured(): void {
        $activation = new Activation( 'wp_update_sdk_my_plugin' );

        $this->assertSame( 'wp_update_sdk_my_plugin', $activation->option_name() );
    }

    public function test_state_can_be_saved_loaded_and_deleted(): void {
        $activation = new Activation( 'wp_update_sdk_my_plugin' );

        $activation->save(
            [
                'license_key' => 'abc123',
                'status'      => 'active',
            ],
        );

        $this->assertSame(
            [
                'license_key' => 'abc123',
                'status'      => 'active',
            ],
            $activation->get(),
        );

        $activation->delete();

        $this->assertSame( [], $activation->get() );
    }
}
