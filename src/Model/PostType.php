<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class PostType - Models data for PostTypes
 *
 * @property bool          $canExport
 * @property bool          $deleteWithUser
 * @property ?string       $description
 * @property bool          $excludeFromSearch
 * @property ?string       $graphqlPluralName
 * @property ?string       $graphqlSingleName
 * @property bool          $hasArchive
 * @property ?bool         $hierarchical
 * @property ?string       $id
 * @property object        $labels
 * @property ?string       $menuIcon
 * @property ?int          $menuPosition
 * @property ?string       $name
 * @property ?bool         $public
 * @property bool          $publiclyQueryable
 * @property ?string       $restBase
 * @property ?string       $restControllerClass
 * @property bool          $showInAdminBar
 * @property bool          $showInGraphql
 * @property bool          $showInMenu
 * @property bool          $showInNavMenus
 * @property bool          $showInRest
 * @property bool          $showUi
 * @property string[]|null $taxonomies
 *
 * Aliases:
 * @property ?string       $graphql_plural_name
 * @property ?string       $graphql_single_name
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Post_Type>
 */
class PostType extends Model {
	/**
	 * PostType constructor.
	 *
	 * @param \WP_Post_Type $post_type The incoming post type to model.
	 */
	public function __construct( \WP_Post_Type $post_type ) {
		$this->data = $post_type;

		$allowed_restricted_fields = [
			'id',
			'name',
			'description',
			'hierarchical',
			'slug',
			'taxonomies',
			'graphql_single_name',
			'graphqlSingleName',
			'graphql_plural_name',
			'graphqlPluralName',
			'showInGraphql',
			'isRestricted',
			'uri',
			'isPostsPage',
			'isFrontPage',
			'label',
		];

		$capability = isset( $post_type->cap->edit_posts ) ? $post_type->cap->edit_posts : 'edit_posts';

		parent::__construct( $capability, $allowed_restricted_fields );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function is_private() {
		if ( false === $this->data->public && ( ! isset( $this->data->cap->edit_posts ) || ! current_user_can( $this->data->cap->edit_posts ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'canExport'           => function () {
					return true === $this->data->can_export;
				},
				'deleteWithUser'      => function () {
					return true === $this->data->delete_with_user;
				},
				'description'         => function () {
					return ! empty( $this->data->description ) ? $this->data->description : '';
				},
				'excludeFromSearch'   => function () {
					return true === $this->data->exclude_from_search;
				},
				'graphqlPluralName'   => function () {
					return ! empty( $this->data->graphql_plural_name ) ? $this->data->graphql_plural_name : null;
				},
				'graphqlSingleName'   => function () {
					return ! empty( $this->data->graphql_single_name ) ? $this->data->graphql_single_name : null;
				},
				'hasArchive'          => function () {
					return ! empty( $this->uri );
				},
				'hierarchical'        => function () {
					return true === $this->data->hierarchical || ! empty( $this->data->hierarchical );
				},
				'id'                  => function () {
					return ! empty( $this->name ) ? Relay::toGlobalId( 'post_type', $this->name ) : null;
				},
				// If the homepage settings are to set to
				'isPostsPage'         => function () {
					// the "post" ContentType is always represented as isPostsPage
					return 'post' === $this->name;
				},
				'isFrontPage'         => function () {
					if (
						'post' === $this->name &&
						(
							'posts' === get_option( 'show_on_front', 'posts' ) ||
							empty( (int) get_option( 'page_on_front', 0 ) )
						)
					) {
						return true;
					}

					return false;
				},
				'label'               => function () {
					return ! empty( $this->data->label ) ? $this->data->label : null;
				},
				'labels'              => function () {
					return get_post_type_labels( $this->data );
				},
				'menuIcon'            => function () {
					return ! empty( $this->data->menu_icon ) ? $this->data->menu_icon : null;
				},
				'menuPosition'        => function () {
					return ! empty( $this->data->menu_position ) ? $this->data->menu_position : null;
				},
				'name'                => function () {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'public'              => function () {
					return ! empty( $this->data->public ) ? (bool) $this->data->public : null;
				},
				'publiclyQueryable'   => function () {
					return true === $this->data->publicly_queryable;
				},
				'restBase'            => function () {
					return ! empty( $this->data->rest_base ) ? $this->data->rest_base : null;
				},
				'restControllerClass' => function () {
					return ! empty( $this->data->rest_controller_class ) ? $this->data->rest_controller_class : null;
				},
				'showInAdminBar'      => function () {
					return true === $this->data->show_in_admin_bar;
				},
				'showInGraphql'       => function () {
					return true === $this->data->show_in_graphql;
				},
				'showInMenu'          => function () {
					return true === $this->data->show_in_menu;
				},
				'showInNavMenus'      => function () {
					return true === $this->data->show_in_nav_menus;
				},
				'showInRest'          => function () {
					return true === $this->data->show_in_rest;
				},
				'showUi'              => function () {
					return true === $this->data->show_ui;
				},
				'taxonomies'          => function () {
					$object_taxonomies = get_object_taxonomies( $this->data->name );
					return ! empty( $object_taxonomies ) ? $object_taxonomies : null;
				},
				'uri'                 => function () {
					$link = get_post_type_archive_link( $this->data->name );
					return ! empty( $link ) ? trailingslashit( str_ireplace( home_url(), '', $link ) ) : null;
				},

				// Aliases.
				'graphql_plural_name' => function () {
					return $this->graphqlPluralName;
				},
				'graphql_single_name' => function () {
					return $this->graphqlSingleName;
				},
			];
		}
	}
}
