<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class EnqueuedScriptsConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class EnqueuedScriptsConnectionResolver extends AbstractConnectionResolver {

	/**
	 * Get the IDs from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_ids() {
		return $this->get_query();
	}

	/**
	 * @return array|void
	 */
	public function get_query_args() {
		// If any args are added to filter/sort the connection
	}

	/**
	 * Get the items from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_query() {
		return $this->source->enqueuedScriptsQueue ?? [];
	}

	/**
	 * Load an individual node by ID
	 *
	 * @param $id
	 *
	 * @return mixed|null|\WPGraphQL\Model\Model
	 * @throws \Exception
	 */
	public function get_node_by_id( $id ) {
		return $this->loader->load( $id );
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'enqueued_script';
	}

	/**
	 * Determine if the model is valid
	 *
	 * @param array $model
	 *
	 * @return bool
	 */
	protected function is_valid_model( $model ) {
		return isset( $model->handle ) ?? false;
	}

	/**
	 * Determine if the offset used for pagination is valid
	 *
	 * @param $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return true;
	}

	/**
	 * Determine if the query should execute
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

}
