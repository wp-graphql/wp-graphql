<?php
namespace WPGraphQL\Data\Connection;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;

/**
 * Class MenuItemConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuItemConnectionResolver extends PostObjectConnectionResolver {

	/**
	 * MenuItemConnectionResolver constructor.
	 *
	 * @param             $source
	 * @param array       $args
	 * @param AppContext  $context
	 * @param ResolveInfo $info
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info, 'nav_menu_item' );
	}

	/**
	 * @param $id
	 *
	 * @return mixed|null|MenuItem|\WPGraphQL\Model\Post
	 * @throws \Exception
	 */
	public function get_node_by_id( $id ) {
		$post = get_post( $id );
		return ! empty( $post ) ? new MenuItem( $post ) : null;
	}

	/**
	 * Returns the query args for the connection to resolve with
	 *
	 * @return array
	 */
	public function get_query_args() {
		$menu_locations = get_theme_mod( 'nav_menu_locations' );

		$query_args = [
			'orderby' => 'menu_order',
			'order'   => 'ASC',
		];

		if ( isset( $this->args['where']['parentDatabaseId'] ) ) {
			$query_args['meta_key']   = '_menu_item_menu_item_parent';
			$query_args['meta_value'] = (int) $this->args['where']['parentDatabaseId'];
		}

		if ( isset( $this->args['where']['parentId'] ) ) {
			$id_parts = Relay::fromGlobalId( $this->args['where']['parentId'] );
			if ( isset( $id_parts['id'] ) ) {
				$query_args['meta_key']   = '_menu_item_menu_item_parent';
				$query_args['meta_value'] = (int) $id_parts['id'];
			}
		}

		$locations = array_unique( array_values( $menu_locations ) );

		if ( isset( $this->args['where']['location'] ) && isset( $menu_locations[ $this->args['where']['location'] ] ) ) {
			$locations = [ $menu_locations[ $this->args['where']['location'] ] ];
		}

		if ( current_user_can( 'edit_theme_options' ) ) {
			$locations = null;
		}

		/**
		 * Only query for menu items in assigned locations
		 */
		if ( ! empty( $locations ) && is_array( $locations ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy'         => 'nav_menu',
					'field'            => 'term_id',
					'terms'            => $locations,
					'include_children' => false,
					'operator'         => 'IN',
				],
			];
		}

		$default = parent::get_query_args();
		$args    = array_merge( $default, $query_args );

		return $args;
	}

}
