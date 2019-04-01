<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class Taxonomy extends Model {

	protected $taxonomy;

	public function __construct( \WP_Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
		parent::__construct( 'TaxonomyObject', $this->taxonomy );
		$this->init();
	}

	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id' => function() {
					return ! empty( $this->taxonomy->name ) ? Relay::toGlobalId( 'taxonomy', $this->taxonomy->name ) : null;
				},
				'object_type' => function() {
					return ! empty( $this->taxonomy->object_type ) ? $this->taxonomy->object_type : null;
				},
				'name' => function() {
					return ! empty( $this->taxonomy->name ) ? $this->taxonomy->name : null;
				},
				'label' => function() {
					return ! empty( $this->taxonomy->label ) ? $this->taxonomy->label : null;
				},
				'description' => function() {
					return ! empty( $this->taxonomy->description ) ? $this->taxonomy->description : '';
				},
				'public' => function() {
					return ! empty( $this->taxonomy->public ) ? (bool) $this->taxonomy->public : true;
				},
				'hierarchical' => function() {
					return ( true === $this->taxonomy->hierarchical ) ? true : false;
				},
				'showUi' => function() {
					return ( true === $this->taxonomy->show_ui ) ? true : false;
				},
				'showInMenu' => function() {
					return ( true === $this->taxonomy->show_in_menu ) ? true : false;
				},
				'showInNavMenus' => function() {
					return ( true === $this->taxonomy->show_in_nav_menus ) ? true : false;
				},
				'showCloud' => function() {
					return ( true === $this->taxonomy->show_tagcloud ) ? true : false;
				},
				'showInQuickEdit' => function() {
					return ( true === $this->taxonomy->show_in_quick_edit ) ? true : false;
				},
				'showInAdminColumn' => function() {
					return ( true === $this->taxonomy->show_admin_column ) ? true : false;
				},
				'showInRest' => function() {
					return ( true === $this->taxonomy->show_in_rest ) ? true : false;
				},
				'restBase' => function() {
					return ! empty( $this->taxonomy->rest_base ) ? $this->taxonomy->rest_base : null;
				},
				'restControllerClass' => function() {
					return ! empty( $this->taxonomy->rest_controller_class ) ? $this->taxonomy->rest_controller_class : null;
				},
				'showInGraphql' => function() {
					return ( true === $this->taxonomy->show_in_graphql ) ? true : false;
				},
				'graphqlSingleName' => function() {
					return ! empty( $this->taxonomy->graphql_single_name ) ? $this->taxonomy->graphql_single_name : null;
				},
				'graphql_single_name' => function() {
					return ! empty( $this->taxonomy->graphql_single_name ) ? $this->taxonomy->graphql_single_name : null;
				},
				'graphqlPluralName' => function() {
					return ! empty( $this->taxonomy->graphql_plural_name ) ? $this->taxonomy->graphql_plural_name : null;
				},
				'graphql_plural_name' => function() {
					return ! empty( $this->taxonomy->graphql_plural_name ) ? $this->taxonomy->graphql_plural_name : null;
				},
			];

			parent::prepare_fields();

		}
	}
}
