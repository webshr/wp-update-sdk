<?php

declare(strict_types=1);

namespace Webshr\WpUpdateSdk;

final class Activation {

    private string $option_name;

    public function __construct( string $option_name ) {
        $this->option_name = $option_name;
    }

    public function option_name(): string {
        return $this->option_name;
    }

    /**
     * Retrieve the stored activation and update state.
     *
     * @return array<string, mixed>
     */
    public function get(): array {
        $state = get_option( $this->option_name, [] );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Save the stored activation and update state.
     *
     * @param array<string, mixed> $state State to persist.
     */
    public function save( array $state ): void {
        update_option( $this->option_name, $state, false );
    }

    public function delete(): void {
        delete_option( $this->option_name );
    }
}
