<?php
namespace DFM\WPGraphQL\Types\TermObject;

use DFM\WPGraphQL\Utils\Fields;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;

/**
 * Class TermObjectQueryType
 *
 * Define TermObjectQueryType
 *
 * @package DFM\WPGraphQL\Types\TermObject
 */
class TermObjectQueryType extends AbstractField {

	/**
	 * taxonomy
	 * @var string
	 * @since 0.0.2
	 */
	protected $taxonomy = 'category';

	/**
	 * query_name
	 * @var string
	 * @since 0.0.2
	 */
	protected $query_name = 'Category';

	/**
	 * taxonomy_object
	 * @var string
	 * @since 0.0.2
	 */
	protected $taxonomy_object;

	/**
	 * terms_per_page
	 * @var int
	 * @since 0.0.2
	 */
	protected $terms_per_page = 10;

	/**
	 * TermObjectQueryType constructor.
	 *
	 * sets default values
	 *
	 * @param array $args
	 */
	public function __construct( $args ) {

		/**
		 * Set the taxonomy from the class instantiation
		 */
		$this->taxonomy = ( ! empty( $args['taxonomy'] ) && taxonomy_exists( $args['taxonomy'] ) ) ? $args['taxonomy'] : $this->taxonomy;

		/**
		 * Set the taxonomy_object from the defined $taxonomy
		 */
		$this->taxonomy_object = get_taxonomy( $this->taxonomy );

		/**
		 * Set the query_name
		 */
		$this->query_name = ( ! empty( $args['query_name'] ) ) ? $args['query_name'] : $this->taxonomy;

		/**
		 * Define the config for the TermObjectQuery
		 * @since 0.0.2
		 */
		$config = [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'resolve' => [ $this, 'resolve' ],
		];

		/**
		 * Pass the config through a filter
		 * @since 0.0.2
		 */
		$config = apply_filters( 'graphql_term_object_query_config', $config, $this->taxonomy, $this->taxonomy_object );

		/**
		 * Pass the $config to the parent __construct
		 */
		parent::__construct( $config );

	}

	/**
	 * getName
	 *
	 * Return the name of the query
	 *
	 * @return mixed
	 */
	public function getName() {
		return $this->query_name;
	}

	/**
	 * getType
	 *
	 * Define the type the query returns
	 *
	 * @return mixed
	 */
	public function getType() {

		/**
		 * Filter the Object type
		 */
		$term_type_query = apply_filters(
			'graphql_term_object_query_type',
			'\DFM\WPGraphQL\Types\TermObject\TermObjectsType',
			$this->taxonomy,
			$this->taxonomy_object
		);

		/**
		 * Return the TermType
		 */
		return new $term_type_query([
			'taxonomy' => $this->taxonomy,
			'query_name' => $this->query_name
		]);

	}

	/**
	 * getDescription
	 *
	 * Define the description for the query
	 */
	public function getDescription(){

		/**
		 * Initial description for the query
		 */
		$description = __( 'Retrieve items of the', 'wp-graphql' ) . ' "' . $this->taxonomy . '" ' . __( 'taxonomy', 'wp-graphql' );

		/**
		 * Filter the description
		 */
		return apply_filters( 'graphql_term_object_query_description', $description );

	}

	/**
	 * resolve
	 *
	 * This defines the resolver for the TermObjectQuery
	 *
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return \WP_Term_Query
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {

		/**
		 * Since the $args are input as an array under the key of
		 * "args" this gets that array so we can access the fields
		 * with the $args var
		 *
		 * @since 0.0.2
		 */
		$args = $args['args'];

		/**
		 * Set the defualt value for the query_args
		 */
		$query_args = [
			'taxonomy' => $this->taxonomy,
			'number' => absint( $this->terms_per_page ),
		];

		/**
		 * This allows query arg defaults to be set before they are merged with user input,
		 * so defaults can be set but can still be overridden by user input
		 *
		 * This allows for settings to be set that can't be overridden by user entry certain contexts
		 */
		$query_args = apply_filter( 'graphql_term_object_query_query_arg_defaults_' . $this->taxonomy, $query_args, $args, $info );

		/**
		 * Combine the default and filtere query_args with the $args manually passed with the query
		 */
		$query_args = wp_parse_args( $args, $query_args );

		/**
		 * Make sure the per_page has a max of 100
		 * as we don't want to overload things
		 */
		$query_args['number'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['number'];

		unset( $query_args['per_page'] );

		/**
		 * Filter the query_args before sending them to the WP_Term_Query
		 *
		 * This allows for settings to be set that can't be overridden by user entry certain contexts
		 * @since 0.0.2
		 */
		$query_args = apply_filters( 'graphql_term_object_query_term_query_args_' . $this->taxonomy, $query_args, $args, $info );

		/**
		 * Run the query and return it
		 */
		return new \WP_Term_Query( $query_args );

	}

	/**
	 * build
	 *
	 * Sets up the $args for the TermObjectQueryType
	 *
	 * @param FieldConfig $config
	 * @since 0.0.2
	 */
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
