<?php
namespace WPGraphQL\Type;

class RootMutation {
	public static function register_type() {
		register_graphql_type( 'RootMutation', new RootMutationType() );
	}
}