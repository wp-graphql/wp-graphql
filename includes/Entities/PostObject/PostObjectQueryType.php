<?php
namespace DFM\WPGraphQL\Entities\PostObject;

use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostObjectQueryType
 *
 * Define the PostObjectQueryType
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.2
 */
class PostObjectQueryType extends AbstractField {

	/**
	 * post_type
	 * @var string
	 * @since 0.0.2
	 */
	protected $post_type = 'post';

	/**
	 * post_type_name
	 * @var string
	 * @since 0.0.2
	 */
	protected $post_type_name = 'Post';

	/**
	 * post_type_object
	 * @var object
	 * @since 0.0.2
	 */
	protected $post_type_object;

	/**
	 * posts_per_page
	 * @var object
	 * @since 0.0.2
	 */
	protected $posts_per_page = 10;

	/**
	 * PostObjectQuery constructor.
	 *
	 * @param array $args
	 * @since 0.0.2
	 */
	public function __construct( $args ) {

		/**
		 * Set the post_type from the class instantiation
		 */
		$this->post_type = ( ! empty( $args['post_type'] ) && post_type_exists( $args['post_type'] ) ) ? $args['post_type'] : $this->post_type;

		/**
		 * Set the post_type_object from the defined post_type
		 */
		$this->post_type_object = get_post_type_object( $this->post_type );

		/**
		 * Take the name from the PostType labels and clean it up to have only letters and numbers
		 * as GraphQL doesn't like any funky characters in the naming
		 */
		$this->post_type_name = preg_replace( '/[^A-Za-z0-9]/i', '', $this->post_type_object->labels->name );

		/**
		 * Set the default posts_per_page
		 */
		$this->posts_per_page = apply_filters( 'wpgraphql_post_object_query_default_posts_per_page', $this->posts_per_page, $this->post_type, $this->post_type_object );

		/**
		 * Define the config for the PostObjectQuery
		 */
		$config = [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'resolve' => [ $this, 'resolve' ]
		];

		/**
		 * Pass the config through a filter
		 */
		$config = apply_filters( 'wpgraphql_post_object_query_config', $config, $this->post_type, $this->post_type_object );

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
	 * @since 0.0.2
	 */
	public function getName() {

		/**
		 * Return the $query_name
		 */
		return $this->post_type_name;

	}

	/**
	 * getType
	 *
	 * Define the Type the query returns
	 *
	 * @return StringType
	 * @since 0.0.2
	 */
	public function getType() {

		/**
		 * Filter the Object type
		 */
		$post_type_query = apply_filters(
			'wpgraphql_post_object_query_type',
			'\DFM\WPGraphQL\Entities\PostObject\PostObjectsType',
			$this->post_type,
			$this->post_type_object
		);

		/**
		 * Return the PostType
		 */
		return new $post_type_query( [ 'post_type' => $this->post_type, 'post_type_name' => $this->post_type_name ] );

	}

	/**
	 * getDescription
	 *
	 * Define the description for the Query
	 *
	 * @return string
	 * @since 0.0.2
	 */
	public function getDescription() {

		/**
		 * Initial description for the query
		 */
		$description = __( 'Retrieve items of the', 'wp-graphql' ) . ' "' . $this->post_type . '" ' . __( 'post type', 'wp-graphql' );

		/**
		 * Filter the description
		 */
		return apply_filters( 'wpgraphql_post_object_query_description', $description );

	}

	/**
	 * resolve
	 *
	 * This defines the resolver for the query
	 *
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return \WP_Query
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

		// Set the default $query_args
		$query_args = [
			'post_type' => esc_html( $this->post_type ),
			'posts_per_page' => absint( $this->posts_per_page ),
		];

		/**
		 * This allows query arg defaults to be set before they are merged with user input,
		 * so defaults can be set but can still be overridden by user input
		 *
		 * This allows for settings to be set that can't be overridden by user entry certain contexts
		 */
		$query_args = apply_filters( 'wpgraphql_post_object_query_query_arg_defaults_' . $this->post_type, $query_args, $args, $info );

		/**
		 * Convert the Schema friendly names to the WP_Query friendly names
		 */
		$query_args['s'] = ! empty( $args['search'] ) ? $args['search'] : $query_args['s'];
		$query_args['p'] = ! empty( $args['id'] ) ? $args['id'] : $query_args['p'];
		$query_args['post_parent'] = ! empty( $args['parent'] ) ? $args['parent'] : $query_args['post_parent'];
		$query_args['post_parent__in'] = ! empty( $args['parent__in'] ) ? $args['parent__in'] : $query_args['post_parent__in'];
		$query_args['post_parent__not_in'] = ! empty( $args['parent__not_in'] ) ? $args['parent__not_in'] : $query_args['post_parent__not_in'];
		$query_args['post__in'] = ! empty( $args['in'] ) ? $args['in'] : $query_args['post__in'];
		$query_args['post__not_in'] = ! empty( $args['not_in'] ) ? $args['not_in'] : $query_args['post__not_in'];
		$query_args['post_name__in'] = ! empty( $args['name_in'] ) ? $args['name_in'] : $query_args['post_name__in'];
		$query_args['post_status'] = ! empty( $args['status'] ) ? $args['status'] : $query_args['post_status'];

		/**
		 * Clean up the Schema friendly names so they're not cluttering the args that are
		 * sent to the WP_Query
		 */
		unset( $args['search'] );
		unset( $args['id'] );
		unset( $args['parent'] );
		unset( $args['parent__in'] );
		unset( $args['in'] );
		unset( $args['not_in'] );
		unset( $args['post_name__in'] );
		unset( $args['status'] );

		// Combine the default $query_arts with the $args passed by the query
		$query_args = wp_parse_args( $args, $query_args );

		// Use the post_type the class was instantiated for, default to 'post'
		$query_args['post_type'] = ( ! empty( $this->post_type ) && post_type_exists( $this->post_type ) ) ? $this->post_type : 'post';

		// Make sure the per_page has a max of 100
		// as we don't want to overload things
		$query_args['posts_per_page'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['posts_per_page'];

		// Clean up the unneeded $query_args
		unset( $query_args['per_page'] );

		/**
		 * Filter the query_args before sending them to the WP_Query
		 *
		 * This allows for settings to be set that can't be overridden by user entry certain contexts
		 */
		$query_args = apply_filters( 'wpgraphql_post_object_query_wpquery_args_' . $this->post_type, $query_args, $args, $info );

		// Run the Query
		$articles = new \WP_Query( $query_args );

		// Return the posts from the WP_Query
		return $articles;

	}

	public function build( FieldConfig $config ) {

		$queryArgs = [
			'post_type' => $this->post_type,
			'post_type_name' => $this->post_type_name,
		];

		$config->addArgument(
			'args',
			[
				'name' => 'args',
				'type' => new PostObjectQueryArgs( $queryArgs ),
				'description' => __( 'Query args for the', 'wp-graphql' ) . ' ' . $this->post_type . ' ' . __( 'post type', 'wp-graphql' ),
			]
		);

	}

}