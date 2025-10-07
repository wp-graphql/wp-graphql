<?php
namespace WPGraphQL\PHPCS\Sniffs\Commenting;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use Composer\Semver\VersionParser;

class ValidSinceTagSniff implements Sniff
{
    /**
     * Version parser instance
     *
     * @var VersionParser
     */
    private $versionParser;

    /**
     * Valid version placeholder strings
     *
     * @var array<string>
     */
    private $validPlaceholders = [
        'next-version',
        'todo',
    ];

    public function __construct()
    {
        $this->versionParser = new VersionParser();
    }

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array<int|string>
     */
    public function register()
    {
        return [T_DOC_COMMENT_TAG, T_COMMENT];
    }

    /**
     * Processes this test when one of its tokens is encountered.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token in the stack.
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Skip files in certain directories
        $file = $phpcsFile->getFilename();
        $skipDirs = [
            'scripts/__tests__',
            '.changeset',
            'docs',
        ];

        foreach ($skipDirs as $dir) {
            if (strpos($file, $dir) !== false) {
                return;
            }
        }

        $tokens = $phpcsFile->getTokens();

        // Only process @since tags
        if ($tokens[$stackPtr]['content'] !== '@since') {
            return;
        }

        // Get the version string (next token after @since)
        $versionPtr = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $stackPtr + 1, null, false, null, true);
        if ($versionPtr === false) {
            return;
        }

        $version = $tokens[$versionPtr]['content'];

        // Split on first space to get just the version number
        $versionParts = preg_split('/\s+/', $version, 2);
        $version = $versionParts[0];

        // Check if it's a valid placeholder
        if (in_array($version, $this->validPlaceholders, true)) {
            // If using old placeholders, suggest next-version
            if ($version !== 'next-version') {
                $fix = $phpcsFile->addFixableWarning(
                    'Please use "@since next-version" instead of "@since %s"',
                    $versionPtr,
                    'OldVersionPlaceholder',
                    [$version]
                );

                if ($fix === true) {
                    $this->fixVersion($phpcsFile, $versionPtr, $version, 'next-version');
                }
            }
            return;
        }

        // Validate semver
        if (!$this->isValidSemver($version)) {
            $fix = $phpcsFile->addFixableError(
                'Version for @since tag must be a valid semver version or "next-version" but got "%s"',
                $versionPtr,
                'InvalidVersion',
                [$version]
            );

            if ($fix === true) {
                $this->fixVersion($phpcsFile, $versionPtr, $version, 'next-version');
            }
        }
    }

    private function fixVersion(File $phpcsFile, $versionPtr, $oldVersion, $newVersion)
    {
        $tokens = $phpcsFile->getTokens();
        $content = $tokens[$versionPtr]['content'];

        // Replace just the version part, keeping any description that follows
        $newContent = str_replace($oldVersion, $newVersion, $content);

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($versionPtr, $newContent);
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
    private function isValidSemver($version)
    {
        try {
            $this->versionParser->normalize($version);
            return true;
        } catch (\UnexpectedValueException $e) {
            return false;
        }
    }
}