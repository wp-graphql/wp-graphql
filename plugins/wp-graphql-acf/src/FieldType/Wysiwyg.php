<?php
namespace WPGraphQL\Acf\FieldType;

class Wysiwyg {

	/**
	 * The acf_the_content callbacks that ran after wpautop at its priority when render_blocks()
	 * removed it, one entry per pass that removed it, most recent last.
	 *
	 * @var array<int,array{priority:int,callbacks:array<int,array{function:callable,accepted_args:int}>}>
	 */
	private static $removed_wpautop = [];

	/**
	 * Register support for the "wysiwyg" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'wysiwyg',
			[
				'graphql_type' => 'String',
			]
		);
	}

	/**
	 * Calls $format, which applies the acf_the_content filter to a WYSIWYG value (directly, or
	 * through ACF's formatting), with any block markup in the value rendered the way the_content
	 * renders it: do_blocks() runs before ACF's wpautop, and wpautop is skipped for the rest of that
	 * pass, so it doesn't wrap the rendered blocks in paragraphs.
	 *
	 * @param callable $format Applies the acf_the_content filter to the value.
	 *
	 * @return mixed What $format returns.
	 *
	 * @since x-release-please-version
	 */
	public static function format_with_blocks_rendered( callable $format ) {
		$depth = count( self::$removed_wpautop );

		\add_filter( 'acf_the_content', [ self::class, 'render_blocks' ], 9 );

		try {
			return $format();
		} finally {
			\remove_filter( 'acf_the_content', [ self::class, 'render_blocks' ], 9 );

			// If a pass didn't finish, its wpautop is still removed.
			$unfinished = count( self::$removed_wpautop ) - $depth;
			for ( $i = 0; $i < $unfinished; $i++ ) {
				self::restore_wpautop();
			}
		}
	}

	/**
	 * Renders the block markup in a value passed through the acf_the_content filter, and removes
	 * wpautop for the rest of the pass, as do_blocks() does for the_content. restore_wpautop() adds
	 * it back after wpautop's priority.
	 *
	 * @param mixed $content The value being filtered.
	 *
	 * @return mixed
	 *
	 * @since x-release-please-version
	 */
	public static function render_blocks( $content ) {
		if ( ! is_string( $content ) || ! \has_blocks( $content ) ) {
			return $content;
		}

		// Render first, so values formatted while the blocks render still get wpautop.
		$content = \do_blocks( $content );

		$priority = \has_filter( 'acf_the_content', 'wpautop' );
		if ( false === $priority ) {
			return $content;
		}

		// Remember the callbacks after wpautop at its priority, so restoring it keeps their order.
		// ACF's shortcode_unautop is one of them, and only works after wpautop.
		$callbacks = [];
		$after     = false;
		foreach ( $GLOBALS['wp_filter']['acf_the_content']->callbacks[ $priority ] ?? [] as $callback ) {
			if ( $after ) {
				$callbacks[] = $callback;
			} elseif ( 'wpautop' === $callback['function'] ) {
				$after = true;
			}
		}

		\remove_filter( 'acf_the_content', 'wpautop', $priority );
		self::$removed_wpautop[] = [
			'priority'  => $priority,
			'callbacks' => $callbacks,
		];
		\add_filter( 'acf_the_content', [ self::class, 'restore_wpautop' ], $priority + 1 );

		return $content;
	}

	/**
	 * Adds back the wpautop callback render_blocks() removed most recently, in its place among the
	 * callbacks at its priority.
	 *
	 * Runs on the acf_the_content filter after wpautop's priority, like core's
	 * _restore_wpautop_hook() does for the_content.
	 *
	 * @param mixed $content The value being filtered, returned unchanged.
	 *
	 * @return mixed
	 *
	 * @since x-release-please-version
	 */
	public static function restore_wpautop( $content = null ) {
		$removed = array_pop( self::$removed_wpautop );
		if ( null === $removed ) {
			return $content;
		}

		$priority = $removed['priority'];
		\remove_filter( 'acf_the_content', [ self::class, 'restore_wpautop' ], $priority + 1 );

		// A callback added to a priority runs after the ones already there, so move the callbacks
		// that followed wpautop back behind it.
		$callbacks = array_filter(
			$removed['callbacks'],
			static function ( $callback ) use ( $priority ) {
				return \remove_filter( 'acf_the_content', $callback['function'], $priority );
			}
		);

		\add_filter( 'acf_the_content', 'wpautop', $priority );
		foreach ( $callbacks as $callback ) {
			\add_filter( 'acf_the_content', $callback['function'], $priority, $callback['accepted_args'] );
		}

		return $content;
	}
}
