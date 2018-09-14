<?php
namespace WPGraphQL\Type;

class TaxonomyUnion {
	public static function register_type() {
		register_graphql_union_type( 'TaxonomyUnion', [
			''
		]);
	}
}