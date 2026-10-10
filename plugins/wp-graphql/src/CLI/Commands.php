<?php
/**
 * WPGraphQL CLI Commands
 *
 * @package WPGraphQL
 * @since 2.7.0
 */

namespace WPGraphQL\CLI;

use WPGraphQL;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class - Commands
 */
class Commands extends \WP_CLI_Command {
	/**
	 * Generate a static schema.
	 *
	 * Writes the schema in the GraphQL Schema Definition Language. Without --output, the schema is
	 * written to schema.graphql in the server's temporary directory.
	 *
	 * [--output=<output>]
	 * : The file path to save the schema to.
	 *
	 * @todo: Provide alternative formats (AST? INTROSPECTION JSON?) and options for output file-type?
	 * @todo: Add Unit Tests
	 *
	 * ## EXAMPLE
	 *
	 *     # Generate a static schema
	 *     $ wp graphql generate-static-schema
	 *
	 *     # Generate a static schema and save it to a specific file
	 *     $ wp graphql generate-static-schema --output=/path/to/file.graphql
	 *
	 * @alias generate
	 * @subcommand generate-static-schema
	 *
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function generate_static_schema( $args, $assoc_args ): void {

		// Check if the output flag is set
		if ( isset( $assoc_args['output'] ) ) {
			// Check if the output file path is writable and its parent directory exists
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable -- this can be run anywhere.
			if ( ! is_writable( dirname( $assoc_args['output'] ) ) ) {
				\WP_CLI::error( 'The output file path is not writable or its parent directory does not exist.' );
			}
			$file_path = $assoc_args['output'];
		} else {
			$file_path = get_temp_dir() . 'schema.graphql';
		}

		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}

		/**
		 * Fires when initializing a GraphQL request from WP-CLI.
		 *
		 * @hookGroup request-lifecycle
		 * @since 0.0.32
		 */
		do_action( 'init_graphql_request' );

		/**
		 * Generate the Schema
		 */
		\WP_CLI::line( 'Getting the Schema...' );

		// Set the introspection query flag
		WPGraphQL::set_is_introspection_query( true );

		// Get the schema
		$schema = WPGraphQL::get_schema();

		/**
		 * Format the Schema
		 */
		\WP_CLI::line( 'Formatting the Schema...' );
		$printed = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );

		/**
		 * Save the Schema to the file
		 */
		\WP_CLI::line( 'Saving the Schema...' );

		file_put_contents( $file_path, $printed ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- we verified the path is writable above.

		// Reset the introspection query flag
		WPGraphQL::set_is_introspection_query( false );

		/**
		 * All done!
		 */
		\WP_CLI::success( sprintf( 'All done. Schema output to %s.', $file_path ) );
	}

	/**
	 * Show or record the status of the WPGraphQL settings review.
	 *
	 * The settings review walks administrators through settings for access, request limits and
	 * debugging, and they're invited to it until it's completed or skipped. `skip` records every
	 * setting currently in the review as reviewed without changing any settings, for example on
	 * sites whose settings are managed in code. On multisite, it applies to the site given by `--url`.
	 *
	 * <action>
	 * : What to do.
	 * ---
	 * options:
	 *   - status
	 *   - skip
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show whether the settings review has been completed or skipped
	 *     $ wp graphql settings-review status
	 *
	 *     # Record the settings review as skipped, without changing any settings
	 *     $ wp graphql settings-review skip
	 *
	 * @subcommand settings-review
	 *
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function settings_review( $args, $assoc_args ): void {
		unset( $assoc_args );

		$settings = new \WPGraphQL\Admin\Settings\Settings();
		$settings->init();
		$settings->register_settings();
		$settings->settings_api->init_registry();

		$settings_review = new \WPGraphQL\Admin\SettingsReview\SettingsReview( $settings->settings_api );
		$action          = $args[0] ?? 'status';

		if ( 'skip' === $action ) {
			$settings_review->record_review( 'skipped' );
			\WP_CLI::success( 'Recorded the settings review as skipped. No settings were changed.' );
			return;
		}

		$state      = \WPGraphQL\Admin\SettingsReview\SettingsReview::get_state();
		$unreviewed = $settings_review->get_unreviewed_field_keys();

		if ( empty( $state['status'] ) ) {
			\WP_CLI::log( 'The settings review has not been completed or skipped.' );
		} else {
			\WP_CLI::log(
				sprintf(
					'The settings review was %1$s on %2$s.',
					$state['status'],
					isset( $state['updated_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $state['updated_at'] ) . ' UTC' : 'an unknown date'
				)
			);
		}

		\WP_CLI::log( sprintf( 'Settings waiting to be reviewed: %d', count( $unreviewed ) ) );

		foreach ( $unreviewed as $key ) {
			\WP_CLI::log( '  ' . $key );
		}
	}
}
