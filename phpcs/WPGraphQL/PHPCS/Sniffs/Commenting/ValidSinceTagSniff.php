<?php

namespace WPGraphQL\PHPCS\Sniffs\Commenting;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ValidSinceTagSniff implements Sniff
{
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
		return [ T_DOC_COMMENT_TAG, T_COMMENT ];
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
			'docs',
		];

		foreach ( $skipDirs as $dir ) {
			if ( false !== strpos( $file, $dir ) ) {
				return;
			}
		}

		$tokens = $phpcsFile->getTokens();

		// Only process @since tags.
		if ( '@since' !== $tokens[ $stackPtr ]['content'] ) {
			return;
		}

		// Get the version string (next token after @since).
		$versionPtr = $phpcsFile->findNext( T_DOC_COMMENT_STRING, ( $stackPtr + 1 ), null, false, null, true );
		if ( false === $versionPtr ) {
			return;
		}

		$version = $tokens[ $versionPtr ]['content'];

		// Split on first space to get just the version number.
		$versionParts = preg_split( '/\s+/', $version, 2 );
		$version      = $versionParts[0];

		// Check if it's a valid placeholder.
		if ( in_array( $version, $this->validPlaceholders, true ) ) {
			return;
		}

		// Validate semver.
		if ( ! $this->isValidSemver( $version ) ) {
			$fix = $phpcsFile->addFixableError(
				'Version for @since tag must be a valid semver version or "x-release-please-version" but got "%s"',
				$versionPtr,
				'InvalidVersion',
				[ $version ]
			);

			if ( true === $fix ) {
				$this->fixVersion( $phpcsFile, $versionPtr, $version, 'x-release-please-version' );
			}
		}
	}

	/**
	 * Replaces the current @since version in-place.
	 *
	 * @param File   $phpcsFile  File being scanned.
	 * @param int    $versionPtr Pointer to the version token.
	 * @param string $oldVersion Old version string.
	 * @param string $newVersion Replacement version string.
	 *
	 * @return bool
	 */
	private function fixVersion( File $phpcsFile, $versionPtr, $oldVersion, $newVersion )
	{
		$tokens  = $phpcsFile->getTokens();
		$content = $tokens[ $versionPtr ]['content'];

		// Replace just the version part, keeping any description that follows.
		$newContent = str_replace( $oldVersion, $newVersion, $content );

		$phpcsFile->fixer->beginChangeset();
		$phpcsFile->fixer->replaceToken( $versionPtr, $newContent );
		$phpcsFile->fixer->endChangeset();

		return true;
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
		return 1 === preg_match(
			'/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/',
			$version
		);
	}
}
