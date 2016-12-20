<?php
namespace DFM\WPGraphQL\Queries\Posts;

use DFM\WPGraphQL\Types\PostObjectType;
use DFM\WPGraphQL\Types\PostsType;
use DFM\WPGraphQL\Types\PostType;
use DFM\WPGraphQL\Queries\Posts\PostObjectQueryArgs;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostObjectQuery
 *
 * Define the PostObjectQuery
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.2
 */
class PostObjectQuery extends AbstractField {

	/**
	 * post_type
	 * @var string
	 * @since 0.0.2
	 */
	protected $post_type = 'post';

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
	public function __construct( $post_type ) {

		/**
		 * Set the post_type from the class instantiation
		 */
		$this->post_type = ( ! empty( $post_type ) && post_type_exists( $post_type ) ) ? $post_type : $this->post_type;

		/**
		 * Set the post_type_object from the defined post_type
		 */
		$this->post_type_object = get_post_type_object( $this->post_type );

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
		 * Pass the query name through a filter
		 * to allow the names of Post Type queries to be customized
		 * if the desire is for them to be something other than
		 * the default
		 */
		$query_name = apply_filters( 'wpgraphql_post_object_query_name', $this->post_type_object->labels->name, $this->post_type_object );

		/**
		 * Ensure the Query only contains letters and numbers
		 */
		$query_name = preg_replace( '/[^A-Za-z0-9]/i', '', $query_name );

		/**
		 * Return the $query_name
		 */
		return $query_name;

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
		 * Return the PostType
		 */
		return new PostObjectType();

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

		// Set the default $query_args
		$query_args = [
			'post_type' => esc_html( $this->post_type ),
			'posts_per_page' => absint( $this->posts_per_page ),
		];

		/**
		 * Convert the Schema friendly names to the WP_Query friendly names
		 */
		$query_args['s'] = $args['search'];
		$query_args['p'] = $args['id'];
		$query_args['post_parent'] = $args['parent'];
		$query_args['post_parent__in'] = $args['parent__in'];
		$query_args['post_parent__not_in'] = $args['parent__not_in'];
		$query_args['post__in'] = $args['in'];
		$query_args['post__not_in'] = $args['not_in'];
		$query_args['name_in'] = $args['post_name__in'];

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

		// Combine the default $query_arts with the $args passed by the query
		$query_args = wp_parse_args( $args, $query_args );

		// Make sure the per_page has a max of 100
		// as we don't want to overload things
		$query_args['posts_per_page'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['posts_per_page'];

		// Clean up the unneeded $query_args
		unset( $query_args['per_page'] );

		// Run the Query
		$articles = new \WP_Query( $query_args );

		// Return the posts from the WP_Query
		return $articles;

	}

	public function build( FieldConfig $config ) {

		$config->addArgument(
			'args',
			[
				'name' => 'args',
				'type' => new PostObjectQueryArgs(),
				'description' => __( 'Query args for the', 'wp-graphql' ) . ' ' . $this->post_type . ' ' . __( 'post type', 'wp-graphql' ),
			]
		);

	}

}