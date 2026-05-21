<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk;

use WP_Error;

final class License {

    private Updater $updater;

    public function __construct( Updater $updater ) {
        $this->updater = $updater;
    }

    /**
     * Activate a license key on the remote server.
     *
     * @param string      $license_key License key to activate.
     * @param string|null $site_url Site URL being activated.
     * @return array<string, mixed>|WP_Error
     */
    public function activate( string $license_key, ?string $site_url = null ) {
        $license_key = sanitize_text_field( $license_key );
        $site_url    = $this->updater->site_url( $site_url );
        $state       = $this->updater->state();

        if ( '' === $license_key ) {
            return new WP_Error( 'license_key_missing', 'Please enter a license key.' );
        }

        $this->set_key( $license_key, $site_url );

        $response = $this->updater->client()->request(
            'POST',
            $this->license_url( 'activate' ),
            [
                'license_key' => $license_key,
                'site_url'    => $site_url,
            ],
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $this->updater->activation()->save(
            array_merge(
                $state,
                [
                    'license_key'   => $license_key,
                    'activation_id' => (string) ( $response['activation_id'] ?? '' ),
                    'site_url'      => $site_url,
                    'status'        => 'active',
                    'channel'       => $state['channel'] ?? 'stable',
                    'checked_at'    => time(),
                    'expires_at'    => (string) ( $response['expires_at'] ?? '' ),
                ],
            ),
        );
        $this->updater->purge_metadata_cache();

        return $response;
    }

    public function set_key( string $license_key, ?string $site_url = null ): void {
        $license_key = sanitize_text_field( $license_key );
        if ( '' === $license_key ) {
            return;
        }

        $state = $this->updater->state();

        $this->updater->activation()->save(
            array_merge(
                $state,
                [
                    'license_key' => $license_key,
                    'site_url'    => $this->updater->site_url( $site_url ),
                    'status'      => $state['status'] ?? 'inactive',
                    'channel'     => $state['channel'] ?? 'stable',
                ],
            ),
        );
        $this->updater->purge_metadata_cache();
    }

    /**
     * Deactivate the current license for this site.
     *
     * @return true|WP_Error
     */
    public function deactivate() {
        $state = $this->updater->state();
        if ( empty( $state['license_key'] ) || empty( $state['activation_id'] ) ) {
            $this->clear();

            return true;
        }

        $response = $this->updater->client()->request(
            'POST',
            $this->license_url( 'deactivate' ),
            [
                'license_key'   => (string) $state['license_key'],
                'activation_id' => (string) $state['activation_id'],
            ],
        );

        if ( is_wp_error( $response ) && 'not_found' !== $response->get_error_code() ) {
            return $response;
        }

        $this->clear();

        return true;
    }

    /**
     * Check the current license state.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function check() {
        $state = $this->updater->state();
        if ( empty( $state['license_key'] ) ) {
            return new WP_Error( 'license_key_missing', 'Please enter a license key.' );
        }

        $response = $this->updater->client()->get(
            add_query_arg(
                [
                    'license_key'   => (string) $state['license_key'],
                    'activation_id' => (string) ( $state['activation_id'] ?? '' ),
                    'site_url'      => (string) ( $state['site_url'] ?? $this->updater->site_url() ),
                ],
                $this->license_url( 'check' ),
            ),
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $this->updater->activation()->save(
            array_merge(
                $state,
                [
                    'status'     => ! empty( $response['active'] ) && ! empty( $response['site_activated'] ) ? 'active' : 'inactive',
                    'channel'    => $state['channel'] ?? 'stable',
                    'checked_at' => time(),
                    'expires_at' => (string) ( $response['expires_at'] ?? ( $state['expires_at'] ?? '' ) ),
                ],
            ),
        );
        $this->updater->purge_metadata_cache();

        return $response;
    }

    public function is_active(): bool {
        $state = $this->updater->state();

        return ( $state['status'] ?? '' ) === 'active'
            && ! empty( $state['license_key'] )
            && ! empty( $state['activation_id'] );
    }

    public function license_key(): string {
        return (string) ( $this->updater->state()['license_key'] ?? '' );
    }

    public function activation_id(): string {
        return (string) ( $this->updater->state()['activation_id'] ?? '' );
    }

    public function clear(): void {
        $state = $this->updater->state();

        unset(
            $state['license_key'],
            $state['activation_id'],
            $state['site_url'],
            $state['status'],
            $state['expires_at'],
            $state['checked_at'],
        );

        $state['channel'] = $state['channel'] ?? 'stable';

        $this->updater->activation()->save( $state );
        $this->updater->purge_metadata_cache();
    }

    private function license_url( string $action ): string {
        return $this->updater->server_url() . '/license/' . rawurlencode( $this->updater->slug ) . '/' . $action;
    }
}
