<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk;

use WP_Error;

final class Client {

    private string $server_url;

    public function __construct( string $server_url ) {
        $this->server_url = rtrim( $server_url, '/' );
    }

    public function server_url(): string {
        return $this->server_url;
    }

    /**
     * Send a JSON request to the update server.
     *
     * @param string               $method HTTP method.
     * @param string               $url Request URL.
     * @param array<string, mixed> $body Optional JSON body.
     * @return array<string, mixed>|WP_Error
     */
    public function request( string $method, string $url, array $body = [] ) {
        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [ 'Accept' => 'application/json' ],
        ];

        if ( [] !== $body ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $this->decode_response( $response );
    }

    /**
     * Fetch JSON from the update server.
     *
     * @param string $url Request URL.
     * @return array<string, mixed>|WP_Error
     */
    public function get( string $url ) {
        return $this->request( 'GET', $url );
    }

    /**
     * Decode a WordPress HTTP response.
     *
     * @param array<string, mixed> $response WordPress HTTP response.
     * @return array<string, mixed>|WP_Error
     */
    private function decode_response( array $response ) {
        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'invalid_response', 'The update server returned an invalid response.' );
        }

        if ( 200 > $status || 300 <= $status ) {
            return new WP_Error(
                (string) ( $data['code'] ?? ( 404 === $status ? 'not_found' : 'update_server_error' ) ),
                (string) ( $data['message'] ?? 'The update server request failed.' ),
                [
                    'status'   => $status,
                    'response' => $data,
                ],
            );
        }

        if ( isset( $data['success'] ) && false === (bool) $data['success'] ) {
            return new WP_Error(
                (string) ( $data['code'] ?? 'update_server_error' ),
                (string) ( $data['message'] ?? 'The update server request failed.' ),
                [
                    'status'   => $status,
                    'response' => $data,
                ],
            );
        }

        return $data;
    }
}
