<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Taxonomy - Models data for taxonomies
 *
 * @property string        $description
 * @property ?string       $graphqlPluralName
 * @property ?string       $graphqlSingleName
 * @property bool          $hierarchical
 * @property ?string       $id
 * @property ?string       $label
 * @property ?string       $name
 * @property string[]|null $object_type
 * @property bool          $public
 * @property ?string       $restBase
 * @property ?string       $restControllerClass
 * @property bool          $showCloud
 * @property bool          $showInAdminColumn
 * @property ?bool         $showInGraphql
 * @property bool          $showInMenu
 * @property bool          $showInNavMenus
 * @property bool          $showInQuickEdit
 * @property bool          $showInRest
 * @property bool          $showUi
 *
 * Aliases:
 * @property ?string       $graphql_plural_name
 * @property ?string       $graphql_single_name
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Taxonomy>
 */
class Taxonomy extends Model {
	/**
	 * Taxonomy constructor.
	 *
	 * @param \WP_Taxonomy $taxonomy The incoming Taxonomy to model.
	 */
	public function __construct( \WP_Taxonomy $taxonomy ) {
		$this->data = $taxonomy;

		$allowed_restricted_fields = [
			'id',
			'name',
			'description',
			'hierarchical',
			'object_type',
			'restBase',
			'graphql_single_name',
			'graphqlSingleName',
			'graphql_plural_name',
			'graphqlPluralName',
			'showInGraphql',
			'isRestricted',
		];

		$capability = isset( $this->data->cap->edit_terms ) ? $this->data->cap->edit_terms : 'edit_terms';

		parent::__construct( $capability, $allowed_restricted_fields );
	}

	/**
	 * {@inheritDoc}
	 *
	 * A taxonomy is never private. It is in the schema only because it was
	 * registered with `show_in_graphql`, and the terms it describes are served to
	 * everyone already, since the Term model has no privacy rule of its own.
	 * Hiding the taxonomy while serving its terms described nothing and protected
	 * nothing.
	 *
	 * Field-level restriction still applies through the capability passed to the
	 * parent constructor: a viewer without `edit_terms` gets this model's allowed
	 * fields and null for the rest, exactly as they already did for a taxonomy
	 * registered `public => true`.
	 */
	protected function is_private() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'description'         => function () {
					return ! empty( $this->data->description ) ? $this->data->description : '';
				},
				'graphqlPluralName'   => function () {
					return ! empty( $this->data->graphql_plural_name ) ? $this->data->graphql_plural_name : null;
				},
				'graphqlSingleName'   => function () {
					return ! empty( $this->data->graphql_single_name ) ? $this->data->graphql_single_name : null;
				},
				'hierarchical'        => function () {
					return true === $this->data->hierarchical;
				},
				'id'                  => function () {
					return ! empty( $this->name ) ? Relay::toGlobalId( 'taxonomy', $this->name ) : null;
				},
				'label'               => function () {
					return ! empty( $this->data->label ) ? $this->data->label : null;
				},
				'name'                => function () {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'object_type'         => function () {
					return ! empty( $this->data->object_type ) ? $this->data->object_type : null;
				},
				'public'              => function () {
					return (bool) $this->data->public;
				},
				'publiclyQueryable'   => function () {
					return (bool) $this->data->publicly_queryable;
				},
				'restBase'            => function () {
					return ! empty( $this->data->rest_base ) ? $this->data->rest_base : null;
				},
				'restControllerClass' => function () {
					return ! empty( $this->data->rest_controller_class ) ? $this->data->rest_controller_class : null;
				},
				'showCloud'           => function () {
					return true === $this->data->show_tagcloud;
				},
				'showInAdminColumn'   => function () {
					return true === $this->data->show_admin_column;
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
				'showInQuickEdit'     => function () {
					return true === $this->data->show_in_quick_edit;
				},
				'showInRest'          => function () {
					return true === $this->data->show_in_rest;
				},
				'showUi'              => function () {
					return true === $this->data->show_ui;
				},

				// Aliases
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
