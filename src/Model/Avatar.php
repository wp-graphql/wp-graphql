<?php

namespace WPGraphQL\Model;

/**
 * Class Avatar - Models data for avatars
 *
 * @property int    $size
 * @property int    $height
 * @property int    $width
 * @property string $default
 * @property bool   $forceDefault
 * @property string $rating
 * @property string $scheme
 * @property string $extraAttr
 * @property bool   $foundAvatar
 * @property string $url
 *
 * @package WPGraphQL\Model
 */
class Avatar extends Model {

	/**
	 * Stores the incoming avatar to be modeled
	 *
	 * @var array $avatar
	 * @access protected
	 */
	protected $avatar;

	/**
	 * Avatar constructor.
	 *
	 * @param array $avatar The incoming avatar to be modeled
	 *
	 * @throws \Exception
	 * @access public
	 */
	public function __construct( $avatar ) {
		$this->avatar = $avatar;
		parent::__construct( 'AvatarObject', $avatar );
		$this->init();
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
				'size' => function() {
					return ! empty( $this->avatar['size'] ) ? absint( $this->avatar['size'] ) : null;
				},
				'height' => function() {
					return ! empty( $this->avatar['height'] ) ? absint( $this->avatar['height'] ) : null;
				},
				'width' => function() {
					return ! empty( $this->avatar['width'] ) ? absint( $this->avatar['width'] ) : null;
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
