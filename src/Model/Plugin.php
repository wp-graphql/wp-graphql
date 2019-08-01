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
		parent::__construct();
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @access protected
	 * @return bool
	 */
	protected function is_private() {

		if ( ! current_user_can( 'update_plugins' ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'          => function() {
					return ! empty( $this->data['Name'] ) ? Relay::toGlobalId( 'plugin', $this->data['Name'] ) : null;
				},
				'name'        => function() {
					return ! empty( $this->data['Name'] ) ? $this->data['Name'] : null;
				},
				'pluginUri'   => function() {
					return ! empty( $this->data['PluginURI'] ) ? $this->data['PluginURI'] : null;
				},
				'description' => function() {
					return ! empty( $this->data['Description'] ) ? $this->data['Description'] : null;
				},
				'author'      => function() {
					return ! empty( $this->data['Author'] ) ? $this->data['Author'] : null;
				},
				'authorUri'   => function() {
					return ! empty( $this->data['AuthorURI'] ) ? $this->data['AuthorURI'] : null;
				},
				'version'     => function() {
					return ! empty( $this->data['Version'] ) ? $this->data['Version'] : null;
				},
			];

		}
	}
}
