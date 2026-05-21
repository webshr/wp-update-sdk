<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Webshr\WpUpdateSdk\Settings;
use Webshr\WpUpdateSdk\Updater;

final class SettingsTest extends TestCase {


    public function test_custom_submenu_redirect_uses_admin_php(): void {
        $settings = new Settings( new Updater( 'my-plugin', 'https://updates.example.com' ) );
        $settings->add_page(
            [
                'type'        => 'submenu',
                'parent_slug' => 'my-plugin',
                'menu_slug'   => 'my-plugin-updates',
            ],
        );

        $this->assertSame(
            'https://example.com/wp-admin/admin.php?page=my-plugin-updates',
            $this->settings_page_url( $settings ),
        );
    }

    public function test_core_submenu_redirect_uses_parent_php_file(): void {
        $settings = new Settings( new Updater( 'my-plugin', 'https://updates.example.com' ) );
        $settings->add_page(
            [
                'type'        => 'submenu',
                'parent_slug' => 'options-general.php',
                'menu_slug'   => 'my-plugin-updates',
            ],
        );

        $this->assertSame(
            'https://example.com/wp-admin/options-general.php?page=my-plugin-updates',
            $this->settings_page_url( $settings ),
        );
    }

    private function settings_page_url( Settings $settings ): string {
        $method = new ReflectionMethod( $settings, 'settings_page_url' );
        $method->setAccessible( true );

        return (string) $method->invoke( $settings );
    }
}
