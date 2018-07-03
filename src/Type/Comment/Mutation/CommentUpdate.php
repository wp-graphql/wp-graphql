<?php

namespace WPGraphQL\Type\Comment\Mutation;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;
/**
 * Class CommentCreate
 *
 * @package WPGraphQL\Type\Comment\Mutation
 */
class CommentUpdate { 

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];
	/**
	 * Defines the create mutation for Comments
	 *
	 * @param \WP_Comment $comment_object
	 *
	 * @return array|mixed
	 */
	public static function mutate( \WP_Comment $comment_object ) {
        return null;
    }
}