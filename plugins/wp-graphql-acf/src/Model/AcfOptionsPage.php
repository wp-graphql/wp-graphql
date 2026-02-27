<?php

namespace WPGraphQL\Acf\Model;

use GraphQLRelay\Relay;
use WPGraphQL\Model\Model;

/**
 * Class AcfOptionsPage - Models data for avatars
 *
 * @property string $id
 * @property string $slug
 * @property string $pageTitle
 * @property string $parentId
 * @property string $capability
 * @property string $acfId
 *
 * @package WPGraphQL\ACF
 */
class AcfOptionsPage extends Model {

	/**
	 * AcfOptionsPage constructor.
	 *
	 * @param array<mixed> $options_page The incoming ACF Options Page to be modeled
	 *
	 * @throws \Exception Throws Exception.
	 */
	public function __construct( array $options_page ) {
		$this->data = $options_page;
		parent::__construct();
	}

	/**
	 * @return array<mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Initialize support for ACF Options Pages loading as a GraphQL Model
	 */
	protected function init(): void {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'slug'      => $this->data['menu_slug'] ?? null,
				'pageTitle' => $this->data['page_title'] ?? null,
				'menuTitle' => $this->data['menu_title'] ?? null,
				'parentId'  => function () {
					return ! empty( $this->data['parent_slug'] ) ? Relay::toGlobalId( 'acf_options_page', $this->data['parent_slug'] ) : null;
				},
				'id'        => function () {
					return Relay::toGlobalId( 'acf_options_page', $this->data['menu_slug'] );
				},
				'acfId'     => $this->data['post_id'] ?? 'options',
			];
		}
	}
}
