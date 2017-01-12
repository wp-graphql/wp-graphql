<?php
namespace DFM\WPGraphQL\Types\PostObject;

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
	 * query_name
	 * @var string
	 * @since 0.0.2
	 */
	protected $query_name = 'Post';

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
	 * sets default values
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
		$this->post_type_object = ( ! empty( $args['post_type_object'] ) ) ? $args['post_type_object'] : get_post_type_object( $this->post_type );

		/**
		 * Set the query_name
		 */
		$this->query_name = ( ! empty( $args['query_name'] ) ) ? $args['query_name'] : $this->post_type;

		/**
		 * Define the config for the PostObjectQuery
		 * @since 0.0.2
		 */
		$config = [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'resolve' => [ $this, 'resolve' ]
		];

		/**
		 * Pass the config through a filter
		 * @since 0.0.2
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
		return $this->query_name;
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
			'\DFM\WPGraphQL\Types\PostObject\PostObjectsType',
			$this->post_type,
			$this->post_type_object
		);

		/**
		 * Return the PostType
		 */
		return new $post_type_query([
			'post_type' => $this->post_type,
			'post_type_object' => $this->post_type_object,
			'query_name' => $this->query_name
		]);

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
	 * This defines the resolver for the PostObjectQuery and maps the "friendly" arg names to the
	 * standard WP arg names.
	 *
	 * This way we can use common system-agnostic names in queries such as
	 * "type" and "per_page" instead of "post_type" and "posts_per_page" which are pretty WP-specific.
	 *
	 * This makes things feel more natural when working with data.
	 * For example, Instead of asking for page.post_name, we can ask for page.slug,
	 * which feels much more natural when what you're after is a slug.
	 *
	 * @todo: this "mapping" of friendly-to-wp-names can be cleaned up and needs to be expanded a bit more.
	 * Probably should look at how the WP-API does it, as they do a similar thing to have cleaner names
	 * in their endpoints
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
		 * @since 0.0.2
		 */
		$query_args = apply_filters( 'wpgraphql_post_object_query_wpquery_args_' . $this->post_type, $query_args, $args, $info );

		/**
		 * Run the query and return it
		 */
		return new \WP_Query( $query_args );

	}

	/**
	 * build
	 *
	 * Sets up the $args for the PostObjectQueryType
	 *
	 * @param FieldConfig $config
	 * @since 0.0.1
	 */
	public function build( FieldConfig $config ) {

		$queryArgs = [
			'post_type' => $this->post_type,
			'query_name' => $this->query_name,
		];

		$config->addArgument(
			'args',
			[
				'name' => 'args',
				'type' => new PostObjectQueryArgs( $queryArgs ),
				'description' => sprintf( __( 'Query args for the %s post type', 'wp-graphql' ), $this->post_type ),
			]
		);

	}

}