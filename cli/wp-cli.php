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

		$schema = WPGraphQL::get_sanitized_schema();
		$printed = \GraphQL\Utils\SchemaPrinter::doPrint( $schema );
		file_put_contents( WPGRAPHQL_PLUGIN_DIR . '/schema.graphql', $printed );
		return $printed;

	}

}