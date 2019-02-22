<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class PostType extends Model {

	protected $post_type;

	public function __construct( \WP_Post_Type $post_type ) {

		$this->post_type = $post_type;

		parent::__construct( 'PostTypeObject', $this->post_type );
		$this->init();

	}

	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id' => function() {
					return ! empty( $this->post_type->name ) ? Relay::toGlobalId( 'postType', $this->post_type->name ) : null;
				},
				'name' => function() {
					return ! empty( $this->post_type->name ) ? $this->post_type->name : null;
				},
				'label' => function() {
					return ! empty( $this->post_type->label ) ? $this->post_type->label : null;
				},
				'labels' => function() {
					return get_post_type_labels( $this->post_type );
				},
				'description' => function() {
					return ! empty( $this->post_type->description ) ? $this->post_type->description : '';
				},
				'public' => function() {
					return ! empty( $this->post_type->public ) ? (bool) $this->post_type->public : null;
				},
				'hierarchical' => function() {
					return ( true === $this->post_type->hierarchical || ! empty( $this->post_type->hierarchical ) ) ? true : false;
				},
				'excludeFromSearch' => function() {
					return ( true === $this->post_type->exclude_from_search ) ? true : false;
				},
				'publiclyQueryable' => function() {
					return ( true === $this->post_type->publicly_queryable ) ? true : false;
				},
				'showUi' => function() {
					return ( true === $this->post_type->show_ui ) ? true : false;
				},
				'showInMenu' => function() {
					return ( true === $this->post_type->show_in_menu ) ? true : false;
				},
				'showInNavMenus' => function() {
					return ( true === $this->post_type->show_in_nav_menus ) ? true : false;
				},
				'showInAdminBar' => function() {
					return ( true === $this->post_type->show_in_admin_bar ) ? true : false;
				},
				'menuPosition' => function() {
					return ! empty( $this->post_type->menu_position ) ? $this->post_type->menu_position : null;
				},
				'menuIcon' => function() {
					return ! empty( $this->post_type->menu_icon ) ? $this->post_type->menu_icon : null;
				},
				'hasArchive' => function() {
					return ( true === $this->post_type->has_archive ) ? true : false;
				},
				'canExport' => function() {
					return ( true === $this->post_type->can_export ) ? true : false;
				},
				'deleteWithUser' => function() {
					return ( true === $this->post_type->delete_with_user ) ? true : false;
				},
				'showInRest' => function() {
					return ( true === $this->post_type->show_in_rest ) ? true : false;
				},
				'restBase' => function() {
					return ! empty( $this->post_type->rest_base ) ? $this->post_type->rest_base : null;
				},
				'restControllerClass' => function() {
					return ! empty( $this->post_type->rest_controller_class ) ? $this->post_type->rest_controller_class : null;
				},
				'showInGraphql' => function() {
					return ( true === $this->post_type->show_in_graphql ) ? true : false;
				},
				'graphqlSingleName' => function() {
					return ! empty( $this->post_type->graphql_single_name ) ? $this->post_type->graphql_single_name : null;
				},
				'graphql_single_name' => function() {
					return ! empty( $this->post_type->graphql_single_name ) ? $this->post_type->graphql_single_name : null;
				},
				'graphqlPluralName' => function() {
					return ! empty( $this->post_type->graphql_plural_name ) ? $this->post_type->graphql_plural_name : null;
				},
				'graphql_plural_name' => function() {
					return ! empty( $this->post_type->graphql_plural_name ) ? $this->post_type->graphql_plural_name : null;
				},
			];

			parent::prepare_fields();
		}
	}
}