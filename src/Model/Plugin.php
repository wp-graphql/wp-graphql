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
 * @property string $path
 *
 * @package WPGraphQL\Model
 */
class Plugin extends Model {

	/**
	 * Stores the incoming plugin data to be modeled
	 *
	 * @var array $data
	 */
	protected $data;

	/**
	 * Plugin constructor.
	 *
	 * @param array $plugin The incoming Plugin data to be modeled
	 *
	 * @throws \Exception
	 */
	public function __construct( $plugin ) {
		$this->data = $plugin;
		parent::__construct();
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @return bool
	 */
	protected function is_private() {

		if ( is_multisite() ) {
				// update_, install_, and delete_ are handled above with is_super_admin().
				$menu_perms = get_site_option( 'menu_items', [] );
			if ( empty( $menu_perms['plugins'] ) && ! current_user_can( 'manage_network_plugins' ) ) {
				return true;
			}
		} elseif ( ! current_user_can( 'activate_plugins' ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Initializes the object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'          => function () {
					return ! empty( $this->data['Path'] ) ? Relay::toGlobalId( 'plugin', $this->data['Path'] ) : null;
				},
				'name'        => function () {
					return ! empty( $this->data['Name'] ) ? $this->data['Name'] : null;
				},
				'pluginUri'   => function () {
					return ! empty( $this->data['PluginURI'] ) ? $this->data['PluginURI'] : null;
				},
				'description' => function () {
					return ! empty( $this->data['Description'] ) ? $this->data['Description'] : null;
				},
				'author'      => function () {
					return ! empty( $this->data['Author'] ) ? $this->data['Author'] : null;
				},
				'authorUri'   => function () {
					return ! empty( $this->data['AuthorURI'] ) ? $this->data['AuthorURI'] : null;
				},
				'version'     => function () {
					return ! empty( $this->data['Version'] ) ? $this->data['Version'] : null;
				},
				'path'        => function () {
					return ! empty( $this->data['Path'] ) ? $this->data['Path'] : null;
				},
			];

		}
	}
}
