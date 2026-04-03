<?php
/**
 * Minimal stubs for PHPStan (WP-CLI not fully loaded during analysis).
 *
 * @package WPGraphQL\PQC
 */

namespace WP_CLI\Utils {

	/**
	 * @param string $message Progress label.
	 * @param int    $count   Total ticks.
	 * @return object{ tick(): void, finish(): void }
	 */
	function make_progress_bar( $message, $count ) {
		return new class() {
			public function tick(): void {
			}

			public function finish(): void {
			}
		};
	}
}
