<?php
namespace DFM\WPGraphQL\Types\TermObject;

use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

class TermObjectQueryType extends AbstractField {

	protected $taxonomy = 'category';

	protected $taxonomy_object;
	
	protected $taxonomy_name = 'Category';

	protected $terms_per_page = 10;

	public function __construct( $args ) {

		$this->taxonomy = ( ! empty( $args['taxonomy'] ) && taxonomy_exists( $args['taxonomy'] ) ) ? $args['taxonomy'] : $this->taxonomy;

		$this->taxonomy_object = get_taxonomy( $this->taxonomy );

		$this->terms_per_page = apply_filters( 'wpgraphql_term_object_query_default_terms_per_page', $this->terms_per_page, $this->taxonomy, $this->taxonomy_object );

		/**
		 * Ensure the Query only contains letters and numbers
		 */
		$this->taxonomy_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $this->taxonomy_object->name );
		$this->taxonomy_name = preg_replace( '/[^A-Za-z0-9]/i', '', ucwords( $this->taxonomy_name ) );

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
		$term_type_query = apply_filters(
			'wpgraphql_term_object_query_type',
			'\DFM\WPGraphQL\Types\TermObject\TermObjectsType',
			$this->taxonomy,
			$this->taxonomy_object
		);

		/**
		 * Return the TermType
		 */
		return new $term_type_query( [ 'taxonomy' => $this->taxonomy, 'taxonomy_name' => $this->taxonomy_name ] );

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

		$queryArgs = [
			'taxonomy' => $this->taxonomy,
			'taxonomy_name' => $this->taxonomy_name,
		];

		$config->addArgument(
			'args',
			[
				'name' => 'args',
				'type' => new TermObjectQueryArgs( $queryArgs ),
				'description' => sprintf( __( 'Query args for the %s taxonomy', 'wp-graphql' ), $this->taxonomy ),
			]
		);
	}

}
