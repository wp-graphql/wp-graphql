<?php

namespace WPGraphQL\PHPCS\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class VersionParameterSniff implements Sniff
{
	/**
	 * Functions to check and their version parameter position (1-based).
	 *
	 * @var array<string,int>
	 */
	private $functions = [
		'_doing_it_wrong'        => 3,
		'_deprecated_function'   => 2,
		'_deprecated_file'       => 2,
		'_deprecated_argument'   => 2,
		'_deprecated_hook'       => 2,
		'_deprecated_class'      => 2,
		'_deprecated_constructor' => 2,
	];

	/**
	 * Valid version placeholder strings.
	 *
	 * @var array<string>
	 */
	private $validPlaceholders = [
		'x-release-please-version',
	];

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array<int|string>
	 */
	public function register()
	{
		return [ T_STRING ];
	}

	/**
	 * Processes this test when one of its tokens is encountered.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 *
	 * @return void
	 */
	public function process( File $phpcsFile, $stackPtr )
	{
		// Skip files in certain directories.
		$file     = $phpcsFile->getFilename();
		$skipDirs = [
			'scripts/__tests__',
			'.changeset',
			'docs',
		];

		foreach ( $skipDirs as $dir ) {
			if ( false !== strpos( $file, $dir ) ) {
				return;
			}
		}

		$tokens       = $phpcsFile->getTokens();
		$functionName = $tokens[ $stackPtr ]['content'];

		// Check if this is one of our target functions.
		if ( ! isset( $this->functions[ $functionName ] ) ) {
			return;
		}

		// Make sure this is a function call.
		$next = $phpcsFile->findNext( T_WHITESPACE, ( $stackPtr + 1 ), null, true );
		if ( T_OPEN_PARENTHESIS !== $tokens[ $next ]['code'] ) {
			return;
		}

		// Get the version parameter position.
		$paramPosition = $this->functions[ $functionName ];

		// Find the version parameter.
		$parameters = $this->getFunctionParameters( $phpcsFile, $stackPtr );

		if ( ! isset( $parameters[ $paramPosition - 1 ] ) ) {
			return;
		}

		$versionParam = $parameters[ $paramPosition - 1 ]['raw'];
		$version      = trim( str_replace( [ "'", '"' ], '', $versionParam ) );

		// Check if it's a valid placeholder.
		if ( in_array( $version, $this->validPlaceholders, true ) ) {
			return;
		}

		// Validate semver.
		if ( ! $this->isValidSemver( $version ) ) {
			$fix = $phpcsFile->addFixableError(
				'Invalid version "%s" in %s(). Must be a valid semver version (optionally prefixed with "v") or "x-release-please-version"',
				$parameters[ $paramPosition - 1 ]['start'],
				'InvalidVersion',
				[ $version, $functionName ]
			);

			if ( true === $fix ) {
				return $this->fixVersionParameter( $phpcsFile, $parameters[ $paramPosition - 1 ] );
			}
		}
	}

	/**
	 * Gets information about function parameters.
	 *
	 * @param File $phpcsFile The file being scanned.
	 * @param int  $stackPtr  The position of the current token in the stack.
	 *
	 * @return array<int,array<string,int|string|null>>
	 */
	private function getFunctionParameters( File $phpcsFile, $stackPtr )
	{
		$tokens = $phpcsFile->getTokens();

		$opener = $phpcsFile->findNext( T_OPEN_PARENTHESIS, $stackPtr );
		if ( false === $opener ) {
			return [];
		}

		if ( ! isset( $tokens[ $opener ]['parenthesis_closer'] ) ) {
			return [];
		}

		$closer       = $tokens[ $opener ]['parenthesis_closer'];
		$parameters   = [];
		$nestingLevel = 0;
		$currentParam = [
			'start' => null,
			'end'   => null,
			'raw'   => '',
		];

		for ( $i = $opener + 1; $i < $closer; $i++ ) {
			// Track nesting of parentheses.
			if ( T_OPEN_PARENTHESIS === $tokens[ $i ]['code'] ) {
				$nestingLevel++;
			}
			if ( T_CLOSE_PARENTHESIS === $tokens[ $i ]['code'] ) {
				$nestingLevel--;
			}

			// Only process commas at the base nesting level.
			if ( T_COMMA === $tokens[ $i ]['code'] && 0 === $nestingLevel ) {
				$currentParam['end'] = $i - 1;
				$parameters[]        = $currentParam;
				$currentParam        = [
					'start' => null,
					'end'   => null,
					'raw'   => '',
				];
				continue;
			}

			if ( null === $currentParam['start'] ) {
				$currentParam['start'] = $i;
			}
			$currentParam['raw'] .= $tokens[ $i ]['content'];
		}

		// Add the last parameter.
		if ( null !== $currentParam['start'] ) {
			$currentParam['end'] = $closer - 1;
			$parameters[]        = $currentParam;
		}

		return $parameters;
	}

	/**
	 * Check if a version string is valid semver.
	 *
	 * @param string $version Version string to check.
	 *
	 * @return bool
	 */
	private function isValidSemver( $version )
	{
		$normalizedVersion = ltrim( $version, 'vV' );

		return 1 === preg_match(
			'/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/',
			$normalizedVersion
		);
	}

	/**
	 * Auto-fix invalid versions to the release placeholder.
	 *
	 * @param File                                 $phpcsFile File being scanned.
	 * @param array{start:int,end:int,raw:string} $parameter Version parameter token range.
	 *
	 * @return bool
	 */
	private function fixVersionParameter( File $phpcsFile, array $parameter )
	{
		$tokens = $phpcsFile->getTokens();

		// Get the full parameter content.
		$content = '';
		for ( $i = $parameter['start']; $i <= $parameter['end']; $i++ ) {
			$content .= $tokens[ $i ]['content'];
		}

		$phpcsFile->fixer->beginChangeset();
		$phpcsFile->fixer->replaceToken( $parameter['start'], "'x-release-please-version'" );

		// Clear any remaining tokens.
		for ( $i = $parameter['start'] + 1; $i <= $parameter['end']; $i++ ) {
			$phpcsFile->fixer->replaceToken( $i, '' );
		}

		$phpcsFile->fixer->endChangeset();

		return true;
	}
}
