<?php
namespace WPGraphQL\Acf\Data\Loader;

use WPGraphQL\Acf\Utils;
use WPGraphQL\Data\Loader\AbstractDataLoader;

class AcfOptionsPageLoader extends AbstractDataLoader {

	/**
	 * @param array<mixed> $keys
	 *
	 * @return array<mixed>
	 * @throws \Exception
	 */
	protected function loadKeys( array $keys ): array {
		if ( empty( $keys ) ) {
			return [];
		}

		$options_pages = Utils::get_acf_options_pages();

		if ( empty( $options_pages ) ) {
			return [];
		}

		$response = [];

		foreach ( $keys as $key ) {
			if ( isset( $options_pages[ $key ] ) ) {
				$response[ $key ] = new \WPGraphQL\Acf\Model\AcfOptionsPage( $options_pages[ $key ] );
			}
		}

		return $response;
	}
}
