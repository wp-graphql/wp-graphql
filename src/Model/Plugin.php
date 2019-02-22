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
	 * @var array $plugin
	 * @access protected
	 */
	protected $plugin;

	/**
	 * Plugin constructor.
	 *
	 * @param array $plugin The incoming Plugin data to be modeled
	 *
	 * @access public
	 * @throws \Exception
	 */
	public function __construct( $plugin ) {

		$this->plugin = $plugin;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 2 );
		}

		parent::__construct( 'PluginObject', $this->plugin );
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

		if ( 'PluginObject' !== $model_name ) {
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
					return ! empty( $this->plugin['Name'] ) ? Relay::toGlobalId( 'plugin', $this->plugin['Name'] ) : null;
				},
				'name' => function() {
					return ! empty( $this->plugin['Name'] ) ? $this->plugin['Name'] : null;
				},
				'pluginUri' => function() {
					return ! empty( $this->plugin['PluginURI'] ) ? $this->plugin['PluginURI'] : null;
				},
				'description' => function() {
					return ! empty( $this->plugin['Description'] ) ? $this->plugin['Description'] : null;
				},
				'author' => function() {
					return ! empty( $this->plugin['Author'] ) ? $this->plugin['Author'] : null;
				},
				'authorUri' => function() {
					return ! empty( $this->plugin['AuthorURI'] ) ? $this->plugin['AuthorURI'] : null;
				},
				'version' => function() {
					return ! empty( $this->plugin['Version'] ) ? $this->plugin['Version'] : null;
				}
			];

			parent::prepare_fields();

		}
	}
}