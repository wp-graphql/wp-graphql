<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class - WPGraphQL_CLI_Command
 *
 * @deprecated since 2.7.0 Use \WPGraphQL\CLI\Commands instead.
 * @codeCoverageIgnore
 */
class WPGraphQL_CLI_Command extends \WPGraphQL\CLI\Commands {

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated since 2.7.0 Use \WPGraphQL\CLI\Commands::generate_static_schema instead.
	 */
	public function generate_static_schema( $args, $assoc_args ): void {
		\WP_CLI::warning( 'The WPGraphQL_CLI_Command class is deprecated since 2.7.0. Please use the \WPGraphQL\CLI\Commands class instead.' );

		parent::generate_static_schema( $args, $assoc_args );
	}
}
