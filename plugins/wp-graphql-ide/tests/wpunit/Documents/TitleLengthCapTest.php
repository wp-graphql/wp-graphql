<?php
/**
 * Tests for the document title-length cap on `graphql_ide_query` writes.
 *
 * Without the cap, a long POST body lands a multi-kilobyte title in the
 * `post_title` TEXT column — bloating the DB and breaking admin UIs
 * (post lists, the IDE tab strip) that can't reasonably display past
 * ~200 chars.
 */

namespace Tests\WPGraphQLIDE\Documents;

class TitleLengthCapTest extends \Codeception\TestCase\WPTestCase {

	public function test_long_title_is_truncated_to_200_chars_on_create(): void {
		$long_title = str_repeat( 'a', 500 );

		$id = wp_insert_post(
			[
				'post_type'    => 'graphql_ide_query',
				'post_status'  => 'draft',
				'post_title'   => $long_title,
				'post_content' => '{ posts { nodes { id } } }',
			],
			true
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$saved = get_post( $id );
		$this->assertSame( 200, mb_strlen( $saved->post_title ) );
		$this->assertSame( str_repeat( 'a', 200 ), $saved->post_title );
	}

	public function test_long_title_is_truncated_on_update(): void {
		$id = wp_insert_post(
			[
				'post_type'    => 'graphql_ide_query',
				'post_status'  => 'draft',
				'post_title'   => 'Short title',
				'post_content' => '{ posts { nodes { id } } }',
			],
			true
		);

		$this->assertIsInt( $id );

		wp_update_post(
			[
				'ID'         => $id,
				'post_title' => str_repeat( 'b', 500 ),
			]
		);

		$saved = get_post( $id );
		$this->assertSame( 200, mb_strlen( $saved->post_title ) );
		$this->assertSame( str_repeat( 'b', 200 ), $saved->post_title );
	}

	public function test_short_title_passes_through_unchanged(): void {
		$id = wp_insert_post(
			[
				'post_type'    => 'graphql_ide_query',
				'post_status'  => 'draft',
				'post_title'   => 'My nice query',
				'post_content' => '{ posts { nodes { id } } }',
			],
			true
		);

		$this->assertSame( 'My nice query', get_post( $id )->post_title );
	}

	public function test_multibyte_titles_dont_split_a_character(): void {
		// 100 emoji = 100 multi-byte glyphs but byte-length far exceeds 200.
		// `mb_substr` with the cap should keep all 100 intact — under the
		// 200-char cap measured in characters, not bytes.
		$emoji_title = str_repeat( '🔥', 100 );

		$id = wp_insert_post(
			[
				'post_type'    => 'graphql_ide_query',
				'post_status'  => 'draft',
				'post_title'   => $emoji_title,
				'post_content' => '{ posts { nodes { id } } }',
			],
			true
		);

		$saved = get_post( $id )->post_title;
		$this->assertSame( $emoji_title, $saved );
		$this->assertSame( 100, mb_strlen( $saved ) );
	}

	public function test_other_post_types_are_not_truncated(): void {
		// Filter must scope to graphql_ide_query — generic posts shouldn't
		// get clipped.
		$id = wp_insert_post(
			[
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => str_repeat( 'c', 500 ),
				'post_content' => 'Hello world',
			],
			true
		);

		$saved = get_post( $id )->post_title;
		$this->assertSame( 500, mb_strlen( $saved ) );
	}
}
