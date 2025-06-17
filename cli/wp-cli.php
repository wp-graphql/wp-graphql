<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WPGraphQL_CLI_Command extends WP_CLI_Command {

	/**
	 * Generate a static schema.
	 *
	 * Defaults to creating a schema.graphql file in the IDL format at the root
	 * of the plugin.
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
	 */
	public function generate_static_schema( $args, $assoc_args ) {

		// Check if the output flag is set
		if ( isset( $assoc_args['output'] ) ) {
			// Check if the output file path is writable and its parent directory exists
			if ( ! is_writable( dirname( $assoc_args['output'] ) ) ) {
			WP_CLI::error( 'The output file path is not writable or its parent directory does not exist.' );
				return;
			}
			$file_path = $assoc_args['output'];
		} else {
			$file_path = get_temp_dir() . 'schema.graphql';
		}

		if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
			define( 'GRAPHQL_REQUEST', true );
		}

		do_action( 'init_graphql_request' );

		/**
		 * Generate the Schema
		 */
		WP_CLI::line( 'Getting the Schema...' );

		// Set the introspection query flag
		WPGraphQL::set_is_introspection_query( true );

		// Get the schema
		$schema = WPGraphQL::get_schema();

		/**
		 * Format the Schema
		 */
		WP_CLI::line( 'Formatting the Schema...' );
		$printed = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );

		/**
		 * Save the Schema to the file
		 */
		WP_CLI::line( 'Saving the Schema...' );

		file_put_contents( $file_path, $printed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// Reset the introspection query flag
		WPGraphQL::set_is_introspection_query( false );

		/**
		 * All done!
		 */
		WP_CLI::success( sprintf( 'All done. Schema output to %s.', $file_path ) );
	}
}

WP_CLI::add_command( 'graphql', 'WPGraphQL_CLI_Command' );
