<?php
/**
 * Basic SemVer implementation.
 *
 * We don't need to do anything fancy here, just enough to compare versions.
 *
 * @package WPGraphQL\Admin\Updates
 * @since 1.30.0
 */

namespace WPGraphQL\Admin\Updates;

/**
 * Class SemVer
 */
final class SemVer {
	/**
	 * Get the release type of the current version of WPGraphQL.
	 *
	 * @param string $old_version The old version of WPGraphQL.
	 * @param string $new_version The new version of WPGraphQL.
	 *
	 * @return 'major'|'minor'|'patch'|'prerelease'|'unknown' The release type.
	 */
	public static function get_release_type( string $old_version, string $new_version ): string {
		$old_version = self::parse( $old_version );
		$new_version = self::parse( $new_version );

		if ( null === $old_version || null === $new_version ) {
			return 'unknown';
		}

		if ( $old_version['major'] < $new_version['major'] ) {
			return 'major';
		}

		if ( $old_version['minor'] < $new_version['minor'] ) {
			return 'minor';
		}

		if ( $old_version['patch'] < $new_version['patch'] ) {
			return 'patch';
		}

		return 'prerelease';
	}

	/**
	 * Parse the version string.
	 *
	 * @param string $version The version string.
	 *
	 * @return ?array{major:int,minor:int,patch:int,prerelease:?string,buildmetadata:?string,version:string}
	 */
	public static function parse( string $version ): ?array {
		/**
		 * Semantic Versioning regex.
		 *
		 * @see https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
		 */
		$regex = '/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildmetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

		if ( ! preg_match( $regex, $version, $matches ) ) {
			return null;
		}

		$prerelease = ! empty( $matches['prerelease'] ) ? self::parse_prerelease_version( $matches['prerelease'] ) : null;

		return [
			'major'         => (int) $matches['major'],
			'minor'         => (int) $matches['minor'],
			'patch'         => (int) $matches['patch'],
			'prerelease'    => $prerelease,
			'buildmetadata' => ! empty( $matches['buildmetadata'] ) ? $matches['buildmetadata'] : null,
			'version'       => sprintf( '%d.%d.%d', $matches['major'], $matches['minor'], $matches['patch'] ),
		];
	}

	/**
	 * Parse the prerelease version string.
	 *
	 * @param string $version The version string.
	 */
	protected static function parse_prerelease_version( string $version ): ?string {
		$separator      = '';
		$release_string = '';

		$version_parts = explode( '.', $version );

		if ( empty( $version_parts ) ) {
			return null;
		}

		foreach ( $version_parts as $part ) {
			// Leading zero is invalid.
			if ( ctype_digit( $part ) ) {
				$part = (int) $part;
			}

			$release_string .= $separator . (string) $part;
			// If this isnt the first round, the separator is a dot.
			$separator = '.';
		}

		return $release_string;
	}
}
