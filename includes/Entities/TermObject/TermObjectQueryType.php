<?php
namespace DFM\WPGraphQL\Entities\TermObject;

use DFM\WPGraphQL\Types\TermObjectType;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

class TermObjectQuery extends AbstractField {

	protected $taxonomy = 'category';

	protected $taxonomy_object;
	
	protected $taxonomy_name;

	protected $terms_per_page = 10;

	public function __construct( $taxonomy ) {

		$this->taxonomy = ( ! empty( $taxonomy ) && taxonomy_exists( $taxonomy ) ) ? $taxonomy : $this->taxonomy;

		$this->taxonomy_object = get_taxonomy( $this->taxonomy );

		$this->terms_per_page = apply_filters( 'wpgraphql_term_object_query_default_terms_per_page', $this->terms_per_page, $this->taxonomy, $this->taxonomy_object );

		/**
		 * Pass the query name through a filter
		 * to allow the names of Post Type queries to be customized
		 * if the desire is for them to be something other than
		 * the default
		 */
		$query_name = apply_filters( 'wpgraphql_term_object_query_name', $this->taxonomy_object->labels->name, $this->taxonomy_object );

		/**
		 * Ensure the Query only contains letters and numbers
		 */
		$this->taxonomy_name = preg_replace( '/[^A-Za-z0-9]/i', '', $query_name );

		$config = [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'resolve' => [ $this, 'resolve' ],
		];

		$config = apply_filters( 'wpgraphql_term_object_query_config', $config, $this->taxonomy, $this->taxonomy_object );

		parent::__construct( $config );

	}

	public function getName() {

		return $this->taxonomy_name;

	}

	public function getType() {
		/**
		 * Filter the Object type
		 */
		$post_type_query = apply_filters(
			'wpgraphql_term_object_query_type',
			'\DFM\WPGraphQL\Entities\TermObject\TermObjectsType',
			$this->taxonomy,
			$this->taxonomy_object
		);

		/**
		 * Return the PostType
		 */
		return new $post_type_query( [ 'taxonomy' => $this->taxonomy, 'taxonomy_name' => $this->taxonomy_name ] );
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {

		$query_args = [
			'taxonomy' => $this->taxonomy,
			'number' => absint( $this->terms_per_page ),
		];

		$query_args = wp_parse_args( $args, $query_args );

		// Make sure the per_page has a max of 100
		// as we don't want to overload things
		$query_args['number'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['number'];

		unset( $query_args['per_page'] );

		return new \WP_Term_Query( $query_args );

	}

	public function build( FieldConfig $config ) {

		$config->addArgument(
			'args',
			[
				'name' => 'args',
				'type' => new TermObjectQueryArgs(),
				'description' => sprintf( __( 'Query args for the %s taxonomy', 'wp-graphql' ), $this->taxonomy ),
			]
		);
	}

}
