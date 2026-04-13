<?php
namespace WPGraphQL\Acf;

/**
 * Handles the resolution of an ACF Field in the GraphQL Schema
 */
class AcfGraphQLFieldResolver {

	/**
	 * @var \WPGraphQL\Acf\AcfGraphQLFieldType
	 */
	protected $acf_graphql_field_type;

	/**
	 * @param \WPGraphQL\Acf\AcfGraphQLFieldType $acf_graphql_field_type
	 */
	public function __construct( AcfGraphQLFieldType $acf_graphql_field_type ) {
		$this->acf_graphql_field_type = $acf_graphql_field_type;
	}

	/**
	 * Get the AcfGraphQLFieldType definition
	 */
	public function get_acf_graphql_field_type(): AcfGraphQLFieldType {
		return $this->acf_graphql_field_type;
	}
}
