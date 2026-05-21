<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk;

final class Updater {



    public string $slug;

    private string $server_url;

    private string $option_prefix;

    private Activation $activation;

    private Client $client;

    private ?License $license = null;

    private ?Settings $settings = null;

    private string $project_identifier;

    private string $version;

    private string $type;

    private string $cache_key;

    /**
     * Create an updater instance.
     *
     * @param string               $slug Project slug.
     * @param string               $server_url Update server base URL.
     * @param array<string, mixed> $args Updater arguments.
     */
    public function __construct( string $slug, string $server_url, array $args = [] ) {
        $this->slug               = sanitize_key( $slug );
        $this->server_url         = rtrim( $server_url, '/' );
        $this->client             = new Client( $this->server_url );
        $this->project_identifier = (string) ( $args['project'] ?? $this->slug );
        $this->version            = (string) ( $args['version'] ?? '0.0.0' );
        $type                     = (string) ( $args['type'] ?? 'plugin' );
        $this->type               = in_array( $type, [ 'plugin', 'theme' ], true ) ? $type : 'plugin';

        $this->set_option_prefix( (string) ( $args['option_prefix'] ?? 'wp_update_sdk' ) );
        $this->refresh_cache_key();
    }

    public function server_url(): string {
        return $this->server_url;
    }

    public function option_prefix(): string {
        return $this->option_prefix;
    }

    public function set_option_prefix( string $option_prefix ): void {
        $this->option_prefix = sanitize_key( $option_prefix );
        $this->activation    = new Activation( sanitize_key( $this->option_prefix . '_' . $this->slug ) );
        $this->refresh_cache_key();
    }

    public function client(): Client {
        return $this->client;
    }

    public function activation(): Activation {
        return $this->activation;
    }

    public function license(): License {
        if ( ! $this->license instanceof License ) {
            $this->license = new License( $this );
        }

        return $this->license;
    }

    public function settings(): Settings {
        if ( ! $this->settings instanceof Settings ) {
            $this->settings = new Settings( $this );
        }

        return $this->settings;
    }

    /**
     * Add the settings page hook.
     *
     * @param array<string, mixed> $args Settings page arguments.
     */
    public function add_page( array $args = [] ): void {
        $this->settings()->add_page( $args );
    }

    public function run(): void {
        if ( 'plugin' === $this->type ) {
            $this->register_plugin_hooks();
        }

        if ( 'theme' === $this->type ) {
            $this->register_theme_hooks();
        }
    }

    public function register_plugin_hooks(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_plugin_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api_filter' ], 10, 3 );
        add_filter( 'upgrader_package_options', [ $this, 'refresh_package_options' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
    }

    public function register_theme_hooks(): void {
        add_filter( 'pre_set_site_transient_update_themes', [ $this, 'check_theme_update' ] );
        add_filter( 'upgrader_package_options', [ $this, 'refresh_package_options' ] );
        add_action( 'upgrader_process_complete', [ $this, 'purge' ], 10, 2 );
    }

    public function metadata_url(): string {
        return add_query_arg( $this->update_query_args(), $this->server_url . '/metadata/' . rawurlencode( $this->slug ) );
    }

    /**
     * Build the metadata URL for update checks.
     *
     * @return array<string, string>
     */
    public function update_query_args(): array {
        $state = $this->state();
        $args  = [
            'channel' => $this->channel(),
        ];

        if ( ! empty( $state['license_key'] ) ) {
            $args['license_key']   = (string) $state['license_key'];
            $args['activation_id'] = (string) ( $state['activation_id'] ?? '' );
            $args['site_url']      = (string) ( $state['site_url'] ?? $this->site_url() );
        }

        return array_filter( $args, static fn ( string $value ): bool => $value !== '' );
    }

    public function channel(): string {
        $channel = (string) ( $this->state()['channel'] ?? 'stable' );

        return in_array( $channel, [ 'stable', 'beta', 'alpha', 'rc' ], true ) ? $channel : 'stable';
    }

    public function set_channel( string $channel ): void {
        $channel = sanitize_key( $channel );
        if ( ! in_array( $channel, [ 'stable', 'beta', 'alpha', 'rc' ], true ) ) {
            $channel = 'stable';
        }

        $this->activation->save( array_merge( $this->state(), [ 'channel' => $channel ] ) );
        $this->refresh_cache_key();
    }

    /**
     * Return the cached update and activation state.
     *
     * @return array<string, mixed>
     */
    public function state(): array {
        return $this->activation->get();
    }

    public function option_name(): string {
        return $this->activation->option_name();
    }

    public function form_action(): string {
        return 'wp_update_sdk_' . $this->slug;
    }

    public function site_url( ?string $site_url = null ): string {
        return rtrim( esc_url_raw( null !== $site_url && '' !== $site_url ? $site_url : home_url() ), '/' );
    }

    public function check_plugin_update( $transient_data ) {
        global $pagenow;

        if ( ! is_object( $transient_data ) ) {
            $transient_data = new \stdClass();
        }

        if ( 'plugins.php' === (string) $pagenow && function_exists( 'is_multisite' ) && is_multisite() ) {
            return $transient_data;
        }

        if ( ! isset( $transient_data->response ) || ! is_array( $transient_data->response ) ) {
            $transient_data->response = [];
        }

        if ( ! isset( $transient_data->no_update ) || ! is_array( $transient_data->no_update ) ) {
            $transient_data->no_update = [];
        }

        if ( isset( $transient_data->response[ $this->project_identifier ] ) ) {
            return $transient_data;
        }

        $versionInfo = $this->get_version_info();
        if ( ! is_object( $versionInfo ) || ! isset( $versionInfo->new_version ) ) {
            return $transient_data;
        }

        if ( ! isset( $versionInfo->plugin ) ) {
            $versionInfo->plugin = $this->project_identifier;
        }

        if ( ! isset( $versionInfo->slug ) ) {
            $versionInfo->slug = $this->slug;
        }

        if ( version_compare( $this->version, (string) $versionInfo->new_version, '<' ) ) {
            $transient_data->response[ $this->project_identifier ] = $versionInfo;
        } else {
            $transient_data->no_update[ $this->project_identifier ] = $versionInfo;
        }

        $transient_data->last_checked                         = time();
        $transient_data->checked[ $this->project_identifier ] = $this->version;

        return $transient_data;
    }

    public function check_theme_update( $transient_data ) {
        if ( ! is_object( $transient_data ) ) {
            $transient_data = new \stdClass();
        }

        if ( ! isset( $transient_data->response ) || ! is_array( $transient_data->response ) ) {
            $transient_data->response = [];
        }

        if ( ! isset( $transient_data->no_update ) || ! is_array( $transient_data->no_update ) ) {
            $transient_data->no_update = [];
        }

        if ( isset( $transient_data->response[ $this->project_identifier ] ) ) {
            return $transient_data;
        }

        $versionInfo = $this->get_version_info();
        if ( ! is_object( $versionInfo ) || ! isset( $versionInfo->new_version ) ) {
            return $transient_data;
        }

        if ( ! isset( $versionInfo->theme ) ) {
            $versionInfo->theme = $this->project_identifier;
        }

        if ( version_compare( $this->version, (string) $versionInfo->new_version, '<' ) ) {
            $transient_data->response[ $this->project_identifier ] = (array) $versionInfo;
        } else {
            $transient_data->no_update[ $this->project_identifier ] = (array) $versionInfo;
        }

        $transient_data->last_checked                         = time();
        $transient_data->checked[ $this->project_identifier ] = $this->version;

        return $transient_data;
    }

    /**
     * Filter plugin information for update details.
     *
     * @param mixed $result Result from WordPress.
     * @param mixed $action Requested action.
     * @param mixed $args Request arguments.
     * @return mixed
     */
    public function plugins_api_filter( $result, $action, $args ) {
        if ( 'plugin' !== $this->type || 'plugin_information' !== (string) $action || ! is_object( $args ) || empty( $args->slug ) || $args->slug !== $this->slug ) {
            return $result;
        }

        $versionInfo = $this->get_version_info();
        if ( ! is_object( $versionInfo ) ) {
            return $result;
        }

        if ( ! isset( $versionInfo->sections ) || ! is_array( $versionInfo->sections ) ) {
            $versionInfo->sections = [
                'description' => 'Version information for ' . $this->slug . '.',
            ];
        }

        return $versionInfo;
    }

    public function purge( $upgrader, array $options ): void {
        if ( ( $options['action'] ?? '' ) !== 'update' ) {
            return;
        }

        if ( ! in_array( (string) ( $options['type'] ?? '' ), [ 'plugin', 'theme' ], true ) ) {
            return;
        }

        delete_site_transient( $this->cache_key );
    }

    public function purge_metadata_cache(): void {
        $previous_cache_key = $this->cache_key;

        $this->refresh_cache_key();

        delete_site_transient( $previous_cache_key );
        delete_site_transient( $this->cache_key );
    }

    /**
     * Refresh the package URL immediately before WordPress downloads it.
     *
     * @param array<string, mixed> $options Upgrader package options.
     * @return array<string, mixed>
     */
    public function refresh_package_options( array $options ): array {
        if ( ! $this->is_current_project_package( $options ) ) {
            return $options;
        }

        $versionInfo = $this->get_project_latest_version();
        if ( ! is_object( $versionInfo ) || empty( $versionInfo->package ) ) {
            return $options;
        }

        $options['package'] = (string) $versionInfo->package;
        $this->set_cached_version_info( $versionInfo );

        return $options;
    }

    /**
     * Get version information, using the cache when available.
     *
     * @return object|false
     */
    private function get_version_info() {
        $versionInfo = $this->get_cached_version_info();
        if ( false === $versionInfo ) {
            $versionInfo = $this->get_project_latest_version();
            $this->set_cached_version_info( $versionInfo );
        }

        return $versionInfo;
    }

    /**
     * Get cached version information.
     *
     * @return object|false
     */
    private function get_cached_version_info() {
        global $pagenow;

        if ( 'update-core.php' === (string) $pagenow ) {
            return false;
        }

        $versionInfo = get_site_transient( $this->cache_key );

        return is_object( $versionInfo ) ? $versionInfo : false;
    }

    private function set_cached_version_info( object|false $value ): void {
        if ( ! is_object( $value ) ) {
            return;
        }

        set_site_transient( $this->cache_key, $value, 3 * HOUR_IN_SECONDS );
    }

    /**
     * Fetch the latest project version from the metadata endpoint.
     *
     * @return object|false
     */
    private function get_project_latest_version() {
        $data = $this->client->get( $this->metadata_url() );
        if ( is_wp_error( $data ) ) {
            return false;
        }

        if ( ! is_array( $data ) || empty( $data['update_available'] ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
            return false;
        }

        if ( version_compare( $this->version, (string) $data['version'], '>=' ) ) {
            return false;
        }

        $versionInfo              = (object) $data;
        $versionInfo->new_version = (string) $data['version'];
        $versionInfo->package     = (string) $data['download_url'];
        $versionInfo->slug        = $this->slug;

        if ( 'plugin' === $this->type ) {
            $versionInfo->plugin = $this->project_identifier;
        }

        if ( 'theme' === $this->type ) {
            $versionInfo->theme = $this->project_identifier;
        }

        if ( empty( $versionInfo->homepage ) ) {
            $versionInfo->homepage = $this->metadata_url();
        }

        if ( ! isset( $versionInfo->sections ) || ! is_array( $versionInfo->sections ) ) {
            $versionInfo->sections = [];
        }

        if ( ! isset( $versionInfo->icons ) || ! is_array( $versionInfo->icons ) ) {
            $versionInfo->icons = [];
        }

        if ( ! isset( $versionInfo->banners ) || ! is_array( $versionInfo->banners ) ) {
            $versionInfo->banners = [];
        }

        return $versionInfo;
    }

    /**
     * Determine whether the upgrader package belongs to this SDK instance.
     *
     * @param array<string, mixed> $options Upgrader package options.
     */
    private function is_current_project_package( array $options ): bool {
        $hook_extra = $options['hook_extra'] ?? [];
        if ( ! is_array( $hook_extra ) ) {
            return false;
        }

        if ( 'update' !== (string) ( $hook_extra['action'] ?? '' ) ) {
            return false;
        }

        if ( $this->type !== (string) ( $hook_extra['type'] ?? '' ) ) {
            return false;
        }

        if ( 'plugin' === $this->type ) {
            return $this->project_identifier === (string) ( $hook_extra['plugin'] ?? '' );
        }

        if ( 'theme' === $this->type ) {
            return $this->project_identifier === (string) ( $hook_extra['theme'] ?? '' );
        }

        return false;
    }

    private function refresh_cache_key(): void {
        $this->cache_key = 'wp_update_sdk_' . md5( $this->metadata_cache_signature() );
    }

    private function metadata_cache_signature(): string {
        $state = $this->state();

        return implode(
            '|',
            [
                $this->server_url,
                $this->slug,
                $this->project_identifier ?? '',
                $this->version ?? '',
                $this->type ?? '',
                $this->channel(),
                (string) ( $state['license_key'] ?? '' ),
                (string) ( $state['activation_id'] ?? '' ),
                (string) ( $state['site_url'] ?? '' ),
                (string) ( $state['status'] ?? '' ),
            ],
        );
    }
}
