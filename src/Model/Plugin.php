<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

/**
 * Class Plugin - Models the Plugin object
 *
 * @property string $id
 * @property string $name
 * @property string $pluginUri
 * @property string $description
 * @property string $author
 * @property string $authorUri
 * @property string $version
 *
 * @package WPGraphQL\Model
 */
class Plugin extends Model {

	/**
	 * Stores the incoming plugin data to be modeled
	 *
	 * @var array $data
	 * @access protected
	 */
	protected $data;

	/**
	 * Plugin constructor.
	 *
	 * @param array $plugin The incoming Plugin data to be modeled
	 *
	 * @access public
	 * @throws \Exception
	 */
	public function __construct( $plugin ) {

		$this->data = $plugin;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 2 );
		}

		parent::__construct();
		$this->init();

	}

	/**
	 * Callback for the graphql_data_is_private filter to determine if the post should be
	 * considered private. Plugins should all be private unless a user has the update_plugins
	 * capability
	 *
	 * @param bool   $private    True or False value if the data should be private
	 * @param string $model_name Name of the model for the data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		if ( ! current_user_can( 'update_plugins') ) {
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
					return ! empty( $this->data['Name'] ) ? Relay::toGlobalId( 'plugin', $this->data['Name'] ) : null;
				},
				'name' => function() {
					return ! empty( $this->data['Name'] ) ? $this->data['Name'] : null;
				},
				'pluginUri' => function() {
					return ! empty( $this->data['PluginURI'] ) ? $this->data['PluginURI'] : null;
				},
				'description' => function() {
					return ! empty( $this->data['Description'] ) ? $this->data['Description'] : null;
				},
				'author' => function() {
					return ! empty( $this->data['Author'] ) ? $this->data['Author'] : null;
				},
				'authorUri' => function() {
					return ! empty( $this->data['AuthorURI'] ) ? $this->data['AuthorURI'] : null;
				},
				'version' => function() {
					return ! empty( $this->data['Version'] ) ? $this->data['Version'] : null;
				}
			];

			parent::prepare_fields();

		}
	}
}