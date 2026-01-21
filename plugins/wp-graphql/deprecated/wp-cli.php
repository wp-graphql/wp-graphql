<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class - WPGraphQL_CLI_Command
 *
 * @deprecated since x-release-please-version Use \WPGraphQL\CLI\Commands instead.
 * @codeCoverageIgnore
 */
class WPGraphQL_CLI_Command extends \WPGraphQL\CLI\Commands {

	/**
	 * {@inheritDoc}
	 *
	 * @deprecated since x-release-please-version Use \WPGraphQL\CLI\Commands::generate_static_schema instead.
	 */
	public function generate_static_schema( $args, $assoc_args ): void {
		\WP_CLI::warning( 'The WPGraphQL_CLI_Command class is deprecated since x-release-please-version. Please use the \WPGraphQL\CLI\Commands class instead.' );

		parent::generate_static_schema( $args, $assoc_args );
	}
}
