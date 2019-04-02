<?php

namespace WPGraphQL\Model;


class Avatar extends Model {

	protected $avatar;

	public function __construct( $avatar ) {
		$this->avatar = $avatar;
		parent::__construct( 'AvatarObject', $avatar );
		$this->init();
	}

	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'size' => function() {
					return ! empty( $this->avatar['size'] ) ? $this->avatar['size'] : null;
				},
				'height' => function() {
					return ! empty( $this->avatar['height'] ) ? $this->avatar['height'] : null;
				},
				'width' => function() {
					return ! empty( $this->avatar['width'] ) ? $this->avatar['width'] : null;
				},
				'default' => function() {
					return ! empty( $this->avatar['default'] ) ? $this->avatar['default'] : null;
				},
				'forceDefault' => function() {
					return ( ! empty( $this->avatar['force_default'] ) && true === $this->avatar['force_default'] ) ? true : false;
				},
				'rating' => function() {
					return ! empty( $this->avatar['rating'] ) ? $this->avatar['rating'] : null;
				},
				'scheme' => function() {
					return ! empty( $this->avatar['scheme'] ) ? $this->avatar['scheme'] : null;
				},
				'extraAttr' => function() {
					return ! empty( $this->avatar['extra_attr'] ) ? $this->avatar['extra_attr'] : null;
				},
				'foundAvatar' => function() {
					return ! empty( $this->avatar['found_avatar'] && true === $this->avatar['found_avatar'] ) ? true : false;
				},
				'url' => function() {
					return ! empty( $this->avatar['url'] ) ? $this->avatar['url'] : null;
				}
			];

			parent::prepare_fields();

		}
	}
}
