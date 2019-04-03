<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

/**
 * Class Taxonomy - Models data for taxonomies
 *
 * @property string $id
 * @property array  $object_type
 * @property string $name
 * @property string $label
 * @property string $description
 * @property bool   $public
 * @property bool   $hierarchical
 * @property bool   $showUi
 * @property bool   $showInMenu
 * @property bool   $showInNavMenus
 * @property bool   $showCloud
 * @property bool   $showInQuickEdit
 * @property bool   $showInAdminColumn
 * @property bool   $showInRest
 * @property string $restBase
 * @property string $restControllerClass
 * @property bool   $showInGraphql
 * @property string $graphqlSingleName
 * @property string $graphql_single_name
 * @property string $graphqlPluralName
 * @property string $graphql_plural_name
 *
 * @package WPGraphQL\Model
 */
class Taxonomy extends Model {

	/**
	 * Stores the incoming WP_Taxonomy object to be modeled
	 *
	 * @var \WP_Taxonomy $data
	 * @access protected
	 */
	protected $data;

	/**
	 * Taxonomy constructor.
	 *
	 * @param \WP_Taxonomy $taxonomy The incoming Taxonomy to model
	 *
	 * @access public
	 * @throws \Exception
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
		];

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( $this->data->cap->edit_terms, $allowed_restricted_fields  );
		$this->init();

	}

	/**
	 * Callback for the graphql_data_is_private filter to determine if the Taxonomy is private or not.
	 *
	 * @param bool          $private    True or False value if the data should be private
	 * @param string        $model_name Name of the model for the data currently being modeled
	 * @param \WP_Taxonomy $data       The Data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name, $data ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		if ( false === $data->public && ! current_user_can( $data->cap->edit_terms ) ) {
			return true;
		}

		return $private;

	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id' => function() {
					return ! empty( $this->data->name ) ? Relay::toGlobalId( 'taxonomy', $this->data->name ) : null;
				},
				'object_type' => function() {
					return ! empty( $this->data->object_type ) ? $this->data->object_type : null;
				},
				'name' => function() {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'label' => function() {
					return ! empty( $this->data->label ) ? $this->data->label : null;
				},
				'description' => function() {
					return ! empty( $this->data->description ) ? $this->data->description : '';
				},
				'public' => function() {
					return ! empty( $this->data->public ) ? (bool) $this->data->public : true;
				},
				'hierarchical' => function() {
					return ( true === $this->data->hierarchical ) ? true : false;
				},
				'showUi' => function() {
					return ( true === $this->data->show_ui ) ? true : false;
				},
				'showInMenu' => function() {
					return ( true === $this->data->show_in_menu ) ? true : false;
				},
				'showInNavMenus' => function() {
					return ( true === $this->data->show_in_nav_menus ) ? true : false;
				},
				'showCloud' => function() {
					return ( true === $this->data->show_tagcloud ) ? true : false;
				},
				'showInQuickEdit' => function() {
					return ( true === $this->data->show_in_quick_edit ) ? true : false;
				},
				'showInAdminColumn' => function() {
					return ( true === $this->data->show_admin_column ) ? true : false;
				},
				'showInRest' => function() {
					return ( true === $this->data->show_in_rest ) ? true : false;
				},
				'restBase' => function() {
					return ! empty( $this->data->rest_base ) ? $this->data->rest_base : null;
				},
				'restControllerClass' => function() {
					return ! empty( $this->data->rest_controller_class ) ? $this->data->rest_controller_class : null;
				},
				'showInGraphql' => function() {
					return ( true === $this->data->show_in_graphql ) ? true : false;
				},
				'graphqlSingleName' => function() {
					return ! empty( $this->data->graphql_single_name ) ? $this->data->graphql_single_name : null;
				},
				'graphql_single_name' => function() {
					return ! empty( $this->data->graphql_single_name ) ? $this->data->graphql_single_name : null;
				},
				'graphqlPluralName' => function() {
					return ! empty( $this->data->graphql_plural_name ) ? $this->data->graphql_plural_name : null;
				},
				'graphql_plural_name' => function() {
					return ! empty( $this->data->graphql_plural_name ) ? $this->data->graphql_plural_name : null;
				},
			];

			parent::prepare_fields();

		}
	}
}
