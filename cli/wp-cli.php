<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
WP_CLI::add_command( 'graphql', 'WPGraphQL_CLI_Command' );

class WPGraphQL_CLI_Command extends WP_CLI_Command {

	/**
	 * Generate a static schema.
	 *
	 * Defaults to creating a schema.graphql file in the IDL format at the root
	 * of the plugin.
	 *
	 * @todo: Provide alternative formats (AST? INTROSPECTION JSON?) and options for output location/file-type?
	 * @todo: Add Unit Tests
	 *
	 * @subcommand generate-static-schema
	 * @param $args
	 * @param $assoc_args
	 * @return string
	 */
	public function generate_static_schema( $args, $assoc_args ) {

		/**
		 * Set the file path for where to save the static schema
		 */
		$file_path = WPGRAPHQL_PLUGIN_DIR . 'schema.graphql';

		/**
		 * Generate the Schema
		 */
		WP_CLI::line( __( 'Getting the Schema...', 'wp-graphql' ) );
		$schema = WPGraphQL::get_schema();

		/**
		 * Format the Schema
		 */
		WP_CLI::line( __( 'Formatting the Schema...', 'wp-graphql' ) );
		$printed = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );

		/**
		 * Save the Schema to the file
		 */
		WP_CLI::line( __( 'Saving the Schema...', 'wp-graphql' ) );
		file_put_contents( $file_path, $printed );

		/**
		 * All done!
		 */
		WP_CLI::success( __( 'All done. Schema output to ' . $file_path, 'wp-graphql' ) );

	}

}