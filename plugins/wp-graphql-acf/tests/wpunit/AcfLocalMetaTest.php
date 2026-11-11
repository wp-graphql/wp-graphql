<?php

/**
 * ACF serves block field values from its local meta, through filters it adds the first time its
 * local meta class is used. The WordPress test case restores the hooks it saved before the first
 * test after every test, so if that class is first used during a test, its filters are gone for
 * every test that follows and block fields resolve from the raw block data instead.
 *
 * The tests run in order: the first uses local meta, the second reads it back.
 */
class AcfLocalMetaTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function testLocalMetaCanBeSetUp(): void {
		acf_setup_meta( [ 'local_meta_test' => 'first' ], 'block_local_meta_test_1', true );

		$this->assertSame( 'first', acf_get_metadata( 'block_local_meta_test_1', 'local_meta_test' ) );

		acf_reset_meta( 'block_local_meta_test_1' );
	}

	public function testLocalMetaCanBeReadInALaterTest(): void {
		acf_setup_meta( [ 'local_meta_test' => 'second' ], 'block_local_meta_test_2', true );

		$this->assertSame( 'second', acf_get_metadata( 'block_local_meta_test_2', 'local_meta_test' ) );

		acf_reset_meta( 'block_local_meta_test_2' );
	}
}
