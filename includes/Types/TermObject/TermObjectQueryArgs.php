<?php
namespace WPGraphQL\Types\TermObject;

use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TermObjectQueryArgs extends AbstractInputObjectType {

	public function getName() {

		$taxonomy_name = $this->getConfig()->get( 'taxonomy_name' );
		$name = ! empty( $taxonomy_name ) ? $taxonomy_name : 'Category';
		return $name . 'Args';
	}

	public function build( $config ) {

		$fields = [
			[
				'name' => 'taxonomy',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Taxonomy name, or array of taxonomies, to which results should be limited.', 'wp-graphql' ),
			],
			[
				'name' => 'object_ids',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Object ID, or array of object IDs. Results will be limited to terms associated with these objects.', 'wp-graphql' ),
			],
			[
				'name' => 'orderby',
				'type' => new StringType(),
				'description' => __( 'Field(s) to order terms by. Accepts term fields (\'name\',\'slug\', \'term_group\', \'term_id\', \'id\', \'description\'), \'count\' for term taxonomy count, \'include\' to match the\'order\' of the $include param, \'meta_value\', \'meta_value_num\', the value of `$meta_key`, the array keys of `$meta_query`, or \'none\' to omit the ORDER BY clause. Defaults to \'name\'.', 'wp-graphql' ),
			],
			[
				'name' => 'order',
				'type' => new StringType(),
				'description' => __( 'Whether to order terms in ascending or descending order. Accepts \'ASC\' (ascending) or \'DESC\' (descending). Default \'ASC\'.', 'wp-graphql' ),
			],
			[
				'name' => 'hide_empty',
				'type' => new BooleanType(),
				'description' => __( 'Whether to hide terms not assigned to any posts. Accepts true or false. Default true', 'wp-graphql' ),
			],
			[
				'name' => 'include',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array or comma/space-separated string of term ids to include. Default empty array.', 'wp-graphql' ),
			],
			[
				'name' => 'exclude',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Array or comma/space-separated string of term ids to exclude. If $include is non-empty, $exclude is ignored. Default empty array.', 'wp-graphql' ),
			],
			[
				'name' => 'exclude_tree',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Array or comma/space-separated string of term ids to exclude along with all of their descendant terms. If $include is non-empty, $exclude_tree is ignored. Default empty array.', 'wp-graphql' ),
			],
			[
				'name' => 'per_page',
				'type' => new IntType(),
				'description' => __( 'Maximum number of terms to return. Accepts \'\'|0 (all) or any positive number. Default \'\'|0 (all).', 'wp-graphql' ),
			],
			[
				'name' => 'offset',
				'type' => new IntType(),
				'description' => __( 'The number by which to offset the terms query. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'name',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Optional. Name or array of names to return term(s) for. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'slug',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Optional. Slug or array of slugs to return term(s) for. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'term_taxonomy_id',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Optional. Term taxonomy ID, or array of term taxonomy IDs, to match when querying terms.', 'wp-graphql' ),
			],
			[
				'name' => 'hierarchical',
				'type' => new BooleanType(),
				'description' => __( 'Whether to include terms that have non-empty descendants (even if $hide_empty is set to true). Default true.', 'wp-graphql' ),
			],
			[
				'name' => 'search',
				'type' => new StringType(),
				'description' => __( 'Search criteria to match terms. Will be SQL-formatted with wildcards before and after. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'name__like',
				'type' => new StringType(),
				'description' => __( 'Retrieve terms with criteria by which a term is LIKE `$name__like`. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'description__like',
				'type' => new StringType(),
				'description' => __( 'Retrieve terms where the description is LIKE `$description__like`. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'pad_counts',
				'type' => new BooleanType(),
				'description' => __( 'Whether to pad the quantity of a term\'s children in the quantity of each term\'s "count" object variable. Default false.', 'wp-graphql' ),
			],
			[
				'name' => 'get',
				'type' => new StringType(),
				'description' => __( 'Whether to return terms regardless of ancestry or whether the terms are empty. Accepts \'all\' or empty (disabled). Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'child_of',
				'type' => new IntType(),
				'description' => __( 'Term ID to retrieve child terms of. If multiple taxonomies are passed, $child_of is ignored. Default 0.', 'wp-graphql' ),
			],
			[
				'name' => 'parent',
				'type' => new IntType(),
				'description' => __( 'Parent term ID to retrieve direct-child terms of. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'childless',
				'type' => new BooleanType(),
				'description' => __( 'True to limit results to terms that have no children. This parameter has no effect on non-hierarchical taxonomies. Default false.', 'wp-graphql' ),
			],
			[
				'name' => 'cache_domain',
				'type' => new StringType(),
				'description' => __( 'Unique cache key to be produced when this query is stored in an object cache. Default is \'core\'.', 'wp-graphql' ),
			],
			[
				'name' => 'update_term_meta_cache',
				'type' => new BooleanType(),
				'description' => __( 'Whether to prime meta caches for matched terms. Default true.', 'wp-graphql' ),
			],
			[
				'name' => 'meta_query',
				'type' => new ListType( new StringType() ), //@todo make an actual thing
				'description' => __( 'Optional. Meta query clauses to limit retrieved terms by. See `WP_Meta_Query`. Default empty.', 'wp-graphql' ),
			],
			[
				'name' => 'meta_key',
				'type' => new StringType(),
				'description' => __( 'Limit terms to those matching a specific metadata key. Can be used in conjunction with `$meta_value`.', 'wp-graphql' ),
			],
			[
				'name' => 'meta_value',
				'type' => new StringType(),
				'description' => __( ' Limit terms to those matching a specific metadata value. Usually used in conjunction with `$meta_key`.', 'wp-graphql' ),
			],

		];

		$config->addFields( $fields );

	}
}
