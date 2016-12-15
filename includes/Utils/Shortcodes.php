<?php
namespace DFM\WPGraphQL\Utils;

/**
 * Class Shortcodes
 *
 * This is a utility class that allows shortcodes to be registered
 * for recognition by the GraphQL Schema
 *
 * @package DFM\WPGraphQL\Utils
 */
class Shortcodes {

	/**
	 * registered_shortcodes
	 *
	 * Stores an array of registered shortcodes
	 *
	 * @since 0.0.2
	 */
	$this->registered_shortcodes = array();

	/**
	 * Shortcodes constructor.
	 * @since 0.0.2
	 */
	public function __construct() {

		// Placeholder

	}

	/**
	 * register_shortcode
	 * @since 0.0.2
	 */
	function get_shortcodes() {

		$shortcodes = [
			[
				'name' => 'audio',
				'fields' => [
					[
						'name' => 'src',
						'type' => 'string',
						'description' => 'The source of your audio file. If not included it will auto-populate with the first audio file attached to the post. You can use the following options to define specific filetypes, allowing for graceful fallbacks.',
					],
					[
						'name' => 'loop',
						'type' => 'string',
						'description' => 'Allows for the looping of media.',
					],
					[
						'name' => 'autoplay',
						'type' => 'boolean',
						'description' => 'Causes the media to automatically play as soon as the media file is ready.',
					],
					[
						'name' => 'preload',
						'type' => 'string',
						'description' => 'Specifies if and how the audio should be loaded when the page loads. Defaults to "none"',
					]
				]
			],
			[
				'name' => 'caption',
				'fields' => [
					'id' => [
						'name' => 'id',
						'type' => 'string',
						'description' => 'A unique HTML ID that you can change to use within your CSS',
					],
					[
						'name' => 'class',
						'type' => 'string',
						'description' => 'Custom class that you can use within your CSS',
					],
					[
						'name' => 'align',
						'type' => 'enum',
						'values' => [ 'alignnone', 'aligncenter', 'alignright', 'alignleft' ],
						'description' => 'The alignment of the caption within the post',
					],
					[
						'name' => 'width',
						'type' => 'string',
						'description' => 'How wide the caption should be in pixels. This is a required and must have a value greater than or equal to 1. If not provided, caption processing will not be done and caption content will be passed-through.',
					],
				],
			],
		];

		/**
		 * Populate the registered shortcodes array
		 */
		$this->register_shortcodes = apply_filters( 'DFM\WPGraphQL\Utils\Shortcodes\RegisterShortcodes', $shortcodes );

		return $this->shortcodes;

	}

}