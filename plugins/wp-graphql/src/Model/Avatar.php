<?php

namespace WPGraphQL\Model;

/**
 * Class Avatar - Models data for avatars
 *
 * @property ?string $default
 * @property ?string $extraAttr
 * @property bool    $forceDefault
 * @property bool    $foundAvatar
 * @property ?int    $height
 * @property ?string $rating
 * @property ?string $scheme
 * @property ?int    $size
 * @property ?string $url
 * @property ?int    $width
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<array<string,mixed>>
 */
class Avatar extends Model {
	/**
	 * Avatar constructor.
	 *
	 * @param array<string,mixed> $avatar The incoming avatar to be modeled.
	 */
	public function __construct( array $avatar ) {
		$this->data = $avatar;
		parent::__construct();
	}

	/**
	 * @return bool
	 */
	protected function is_private() {
		$show_avatars = get_option( 'show_avatars' );
		return ! (bool) $show_avatars;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'default'      => function () {
					return ! empty( $this->data['default'] ) ? $this->data['default'] : null;
				},
				'extraAttr'    => function () {
					return ! empty( $this->data['extra_attr'] ) ? $this->data['extra_attr'] : null;
				},
				'forceDefault' => function () {
					return ! empty( $this->data['force_default'] );
				},
				'foundAvatar'  => function () {
					return ! empty( $this->data['found_avatar'] );
				},
				'height'       => function () {
					return ! empty( $this->data['height'] ) ? absint( $this->data['height'] ) : null;
				},
				'rating'       => function () {
					return ! empty( $this->data['rating'] ) ? $this->data['rating'] : null;
				},
				'scheme'       => function () {
					return ! empty( $this->data['scheme'] ) ? $this->data['scheme'] : null;
				},
				'size'         => function () {
					return ! empty( $this->data['size'] ) ? absint( $this->data['size'] ) : null;
				},
				'url'          => function () {
					return ! empty( $this->data['url'] ) ? $this->data['url'] : null;
				},
				'width'        => function () {
					return ! empty( $this->data['width'] ) ? absint( $this->data['width'] ) : null;
				},
			];
		}
	}
}
