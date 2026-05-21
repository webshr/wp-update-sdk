<?php

declare(strict_types=1);

$wpPhpUnitConfig = getenv( 'WP_PHPUNIT__TESTS_CONFIG' );
$wpTestsConfig   = $wpPhpUnitConfig !== false && $wpPhpUnitConfig !== ''
    ? $wpPhpUnitConfig
    : dirname( __DIR__ ) . '/wp-tests-config.php';

if ( is_readable( $wpTestsConfig ) ) {
    define( 'WP_PHPUNIT__TESTS_CONFIG', $wpTestsConfig );
    require_once dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php';

    return;
}

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error
    {

        private string $code;

        private string $message;

        /**
         * @var array<string, mixed>
         */
        private array $data;

        /**
         * @param array<string, mixed> $data
         */
        public function __construct ( string $code = '', string $message = '', array $data = [] )
        {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_code () : string
        {
            return $this->code;
        }

        public function get_error_message () : string
        {
            return $this->message;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_error_data () : array
        {
            return $this->data;
        }
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key ( string $key ) : string
    {
        $key = strtolower( $key );

        return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field ( string $value ) : string
    {
        return trim( strip_tags( $value ) );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error ( mixed $thing ) : bool
    {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option ( string $option, mixed $default = false ) : mixed
    {
        return $GLOBALS['wp_update_sdk_options'][ $option ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option ( string $option, mixed $value, mixed $autoload = null ) : bool
    {
        $GLOBALS['wp_update_sdk_options'][ $option ] = $value;

        return true;
    }
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option ( string $option ) : bool
    {
        unset( $GLOBALS['wp_update_sdk_options'][ $option ] );

        return true;
    }
}

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode ( mixed $value ) : string|false
    {
        return json_encode( $value );
    }
}

if ( ! function_exists( 'add_filter' ) ) {
    function add_filter ( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ) : bool
    {
        $GLOBALS['wp_update_sdk_hooks']['filters'][ $hook_name ][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action ( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ) : bool
    {
        $GLOBALS['wp_update_sdk_hooks']['actions'][ $hook_name ][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if ( ! function_exists( 'get_site_transient' ) ) {
    function get_site_transient ( string $transient ) : mixed
    {
        return $GLOBALS['wp_update_sdk_site_transients'][ $transient ] ?? false;
    }
}

if ( ! function_exists( 'set_site_transient' ) ) {
    function set_site_transient ( string $transient, mixed $value, int $expiration = 0 ) : bool
    {
        $GLOBALS['wp_update_sdk_site_transients'][ $transient ] = $value;

        return true;
    }
}

if ( ! function_exists( 'delete_site_transient' ) ) {
    function delete_site_transient ( string $transient ) : bool
    {
        unset( $GLOBALS['wp_update_sdk_site_transients'][ $transient ] );

        return true;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get ( string $url, array $args = [] ) : array|WP_Error
    {
        $args['method'] = 'GET';

        return wp_remote_request( $url, $args );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg ( array|string $args, mixed $value = null, ?string $url = null ) : string
    {
        if ( is_array( $args ) ) {
            $query_args = $args;
            $url        = (string) $value;
        }
        else {
            $query_args = [ $args => (string) $value ];
            $url        = (string) $url;
        }

        $parsed = parse_url( $url );
        parse_str( (string) ( $parsed['query'] ?? '' ), $query );

        $query = array_merge( $query, $query_args );
        $base  = ( isset( $parsed['scheme'], $parsed['host'] ) ? $parsed['scheme'] . '://' . $parsed['host'] : '' )
            . ( $parsed['path'] ?? '' );

        return $base . ( $query !== [] ? '?' . http_build_query( $query ) : '' );
    }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url ( string $path = '' ) : string
    {
        return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url ( string $url, int $component = -1 ) : mixed
    {
        return parse_url( $url, $component );
    }
}

if ( ! function_exists( 'home_url' ) ) {
    function home_url () : string
    {
        return 'https://example.com';
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    function esc_url_raw ( string $url ) : string
    {
        return $url;
    }
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'get_bloginfo' ) ) {
    function get_bloginfo ( string $show = '' ) : string
    {
        return $show === 'version' ? '6.0' : '';
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename ( string $file ) : string
    {
        return $file;
    }
}

if ( ! function_exists( 'is_multisite' ) ) {
    function is_multisite () : bool
    {
        return (bool) ( $GLOBALS['wp_update_sdk_is_multisite'] ?? false );
    }
}

if ( ! function_exists( 'wp_remote_request' ) ) {
    function wp_remote_request ( string $url, array $args = [] ) : array|WP_Error
    {
        $GLOBALS['wp_update_sdk_last_request'] = [
            'url'  => $url,
            'args' => $args,
        ];

        if ( empty( $GLOBALS['wp_update_sdk_http_queue'] ) ) {
            return new WP_Error( 'missing_mock_response', 'No mock response configured.' );
        }

        return array_shift( $GLOBALS['wp_update_sdk_http_queue'] );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code ( array|WP_Error $response ) : int
    {
        return (int) ( $response['response']['code'] ?? 0 );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body ( array|WP_Error $response ) : string
    {
        return (string) ( $response['body'] ?? '' );
    }
}

$GLOBALS['wp_update_sdk_options']         = [];
$GLOBALS['wp_update_sdk_http_queue']      = [];
$GLOBALS['wp_update_sdk_last_request']    = null;
$GLOBALS['wp_update_sdk_site_transients'] = [];
$GLOBALS['wp_update_sdk_hooks']           = [
    'filters' => [],
    'actions' => [],
];
$GLOBALS['wp_update_sdk_is_multisite']    = false;

require_once dirname( __DIR__ ) . '/src/Activation.php';
require_once dirname( __DIR__ ) . '/src/License.php';
require_once dirname( __DIR__ ) . '/src/Client.php';
require_once dirname( __DIR__ ) . '/src/Settings.php';
require_once dirname( __DIR__ ) . '/src/Updater.php';
