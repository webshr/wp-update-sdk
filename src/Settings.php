<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk;

final class Settings {


    private Updater $updater;

    /**
     * Settings page arguments.
     *
     * @var array<string, mixed>
     */
    private array $args = [];

    public function __construct( Updater $updater ) {
        $this->updater = $updater;
        $this->args    = $this->default_args();
    }

    /**
     * Register the settings page hooks.
     *
     * @param array<string, mixed> $args Settings page arguments.
     */
    public function add_page( array $args = [] ): void {
        $this->args = array_replace( $this->default_args(), $args );

        add_action( 'admin_menu', [ $this, 'register' ] );
        add_action( 'admin_post_' . $this->updater->form_action(), [ $this, 'handle_post' ] );
    }

    public function register(): void {
        $type = (string) $this->args['type'];

        if ( 'menu' === $type ) {
            add_menu_page(
                (string) $this->args['page_title'],
                (string) $this->args['menu_title'],
                (string) $this->args['capability'],
                (string) $this->args['menu_slug'],
                [ $this, 'render' ],
                (string) $this->args['icon_url'],
                $this->args['position'],
            );

            return;
        }

        if ( 'submenu' === $type ) {
            add_submenu_page(
                (string) $this->args['parent_slug'],
                (string) $this->args['page_title'],
                (string) $this->args['menu_title'],
                (string) $this->args['capability'],
                (string) $this->args['menu_slug'],
                [ $this, 'render' ],
                $this->args['position'],
            );

            return;
        }

        add_options_page(
            (string) $this->args['page_title'],
            (string) $this->args['menu_title'],
            (string) $this->args['capability'],
            (string) $this->args['menu_slug'],
            [ $this, 'render' ],
            $this->args['position'],
        );
    }

    public function handle_post(): void {
        $capability = (string) ( $this->args['capability'] ?? 'manage_options' );
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'You do not have permission to manage updates.', 'wp-update-sdk' ) );
        }

        check_admin_referer( $this->updater->form_action() );

        $action  = isset( $_POST['wp_update_sdk_action'] ) ? sanitize_key( wp_unslash( $_POST['wp_update_sdk_action'] ) ) : 'save';
        $channel = isset( $_POST['wp_update_sdk_channel'] ) ? sanitize_key( wp_unslash( $_POST['wp_update_sdk_channel'] ) ) : 'stable';
        $this->updater->set_channel( $channel );

        $result = true;
        if ( 'activate' === $action ) {
            $license_key = isset( $_POST['wp_update_sdk_key'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_update_sdk_key'] ) ) : '';
            $result      = $this->updater->license()->activate( $license_key );
        } elseif ( 'deactivate' === $action ) {
            $result = $this->updater->license()->deactivate();
        } elseif ( 'check' === $action ) {
            $result = $this->updater->license()->check();
        }

        if ( is_wp_error( $result ) ) {
            $this->set_notice( 'error', $result->get_error_message() );
        } else {
            $message = 'deactivate' === $action
                ? 'License deactivated.'
                : ( 'activate' === $action ? 'License activated.' : 'Update settings saved.' );
            $this->set_notice( 'success', $message );
        }

        wp_safe_redirect( $this->redirect_url( $action, ! is_wp_error( $result ) ) );
        exit;
    }

    public function render(): void {
        $state  = $this->updater->state();
        $notice = get_transient( $this->notice_key() );
        delete_transient( $this->notice_key() );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( (string) $this->args['page_title'] ); ?></h1>

            <?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
                <div class="notice notice-<?php echo esc_attr( (string) $notice['type'] ); ?> is-dismissible">
                    <p><?php echo esc_html( (string) $notice['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <div style="max-width: 720px; margin-top: 20px;">
                <div style="background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 24px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e( 'Updates', 'wp-update-sdk' ); ?></h2>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( $this->updater->form_action() ); ?>
                        <input type="hidden" name="action" value="<?php echo esc_attr( $this->updater->form_action() ); ?>">

                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="wp-update-sdk-channel"><?php esc_html_e( 'Release channel', 'wp-update-sdk' ); ?></label>
                                    </th>
                                    <td>
                                        <select id="wp-update-sdk-channel" name="wp_update_sdk_channel">
                                            <?php
                                            foreach ( [
                                                'stable' => 'Stable',
                                                'beta'   => 'Beta',
                                                'alpha'  => 'Alpha',
                                                'rc'     => 'Release candidate',
                                            ] as $value => $label ) :
                                                ?>
                                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->updater->channel(), $value ); ?>>
                                                    <?php echo esc_html( $label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="wp-update-sdk-key"><?php esc_html_e( 'License key', 'wp-update-sdk' ); ?></label>
                                    </th>
                                    <td>
                                        <input
                                            id="wp-update-sdk-key"
                                            class="regular-text"
                                            type="password"
                                            name="wp_update_sdk_key"
                                            value="<?php echo esc_attr( $this->updater->license()->license_key() ); ?>"
                                            autocomplete="off">
                                        <p class="description">
                                            <?php esc_html_e( 'Optional. Updates can still be checked without a license unless the update server requires one.', 'wp-update-sdk' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <p>
                            <strong><?php esc_html_e( 'License status:', 'wp-update-sdk' ); ?></strong>
                            <?php echo $this->updater->license()->is_active() ? esc_html__( 'Active', 'wp-update-sdk' ) : esc_html__( 'Inactive', 'wp-update-sdk' ); ?>
                        </p>

                        <?php if ( ! empty( $state['expires_at'] ) ) : ?>
                            <p>
                                <strong><?php esc_html_e( 'Expires:', 'wp-update-sdk' ); ?></strong>
                                <?php echo esc_html( (string) $state['expires_at'] ); ?>
                            </p>
                        <?php endif; ?>

                        <p class="submit">
                            <button class="button button-primary" type="submit" name="wp_update_sdk_action" value="save">
                                <?php esc_html_e( 'Save settings', 'wp-update-sdk' ); ?>
                            </button>
                            <button class="button" type="submit" name="wp_update_sdk_action" value="activate">
                                <?php echo $this->updater->license()->is_active() ? esc_html__( 'Update license', 'wp-update-sdk' ) : esc_html__( 'Activate license', 'wp-update-sdk' ); ?>
                            </button>
                            <?php if ( '' !== $this->updater->license()->license_key() ) : ?>
                                <button class="button" type="submit" name="wp_update_sdk_action" value="check">
                                    <?php esc_html_e( 'Check license', 'wp-update-sdk' ); ?>
                                </button>
                                <button class="button button-link-delete" type="submit" name="wp_update_sdk_action" value="deactivate">
                                    <?php esc_html_e( 'Deactivate', 'wp-update-sdk' ); ?>
                                </button>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get default settings page arguments.
     *
     * @return array<string, mixed>
     */
    private function default_args(): array {
        return [
            'type'                 => 'options',
            'parent_slug'          => 'options-general.php',
            'page_title'           => 'Updates',
            'menu_title'           => 'Updates',
            'capability'           => 'manage_options',
            'menu_slug'            => $this->updater->slug . '-updates',
            'icon_url'             => '',
            'position'             => null,
            'activated_redirect'   => '',
            'deactivated_redirect' => '',
        ];
    }

    private function notice_key(): string {
        return $this->updater->option_name() . '_notice';
    }

    private function set_notice( string $type, string $message ): void {
        set_transient(
            $this->notice_key(),
            [
                'type'    => $type,
                'message' => $message,
            ],
            60,
        );
    }

    private function redirect_url( string $action, bool $success ): string {
        if ( $success && 'activate' === $action && ! empty( $this->args['activated_redirect'] ) ) {
            return (string) $this->args['activated_redirect'];
        }

        if ( $success && 'deactivate' === $action && ! empty( $this->args['deactivated_redirect'] ) ) {
            return (string) $this->args['deactivated_redirect'];
        }

        return $this->settings_page_url();
    }

    private function settings_page_url(): string {
        $menuSlug = rawurlencode( (string) $this->args['menu_slug'] );
        $type     = (string) ( $this->args['type'] ?? 'options' );

        if ( 'options' === $type ) {
            return admin_url( 'options-general.php?page=' . $menuSlug );
        }

        if ( 'submenu' === $type && ! empty( $this->args['parent_slug'] ) ) {
            $parent_slug = (string) $this->args['parent_slug'];
            $parent_url  = str_ends_with( $parent_slug, '.php' ) ? $parent_slug : 'admin.php';

            return add_query_arg( 'page', $menuSlug, admin_url( $parent_url ) );
        }

        return admin_url( 'admin.php?page=' . $menuSlug );
    }
}
