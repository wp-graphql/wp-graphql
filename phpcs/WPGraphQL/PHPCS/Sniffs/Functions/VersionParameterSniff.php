<?php
namespace WPGraphQL\PHPCS\Sniffs\Functions;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;
use Composer\Semver\VersionParser;

class VersionParameterSniff implements Sniff
{
    /**
     * Version parser instance
     *
     * @var VersionParser
     */
    private $versionParser;

    /**
     * Functions to check and their version parameter position (1-based)
     *
     * @var array<string,int>
     */
    private $functions = [
        '_doing_it_wrong' => 3,
        '_deprecated_function' => 2,
        '_deprecated_file' => 2,
        '_deprecated_argument' => 2,
        '_deprecated_hook' => 2,
        '_deprecated_class' => 2,
        '_deprecated_constructor' => 2,
    ];

    /**
     * Valid version placeholder strings
     *
     * @var array<string>
     */
    private $validPlaceholders = [
        '@since next-version',
        '@since todo',
        '@next-version',
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
        return [T_STRING];
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
        $functionName = $tokens[$stackPtr]['content'];

        // Check if this is one of our target functions
        if (!isset($this->functions[$functionName])) {
            return;
        }

        // Make sure this is a function call
        $next = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        if ($tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        // Get the version parameter position
        $paramPosition = $this->functions[$functionName];

        // Find the version parameter
        $parameters = $this->getFunctionParameters($phpcsFile, $stackPtr);

        if (!isset($parameters[$paramPosition - 1])) {
            return;
        }

        $versionParam = $parameters[$paramPosition - 1]['raw'];
        $version = trim(str_replace(["'", '"'], '', $versionParam));

        // Check if it's a valid placeholder
        if (in_array($version, $this->validPlaceholders, true)) {
            if ($version !== '@since next-version') {
                $fix = $phpcsFile->addFixableWarning(
                    'Please use "@since next-version" instead of "%s"',
                    $parameters[$paramPosition - 1]['start'],
                    'OldVersionPlaceholder',
                    [$version]
                );

                if ($fix === true) {
                    return $this->fixVersionParameter($phpcsFile, $parameters[$paramPosition - 1]);
                }
            }
            return;
        }

        // Validate semver
        if (!$this->isValidSemver($version)) {
            $fix = $phpcsFile->addFixableError(
                'Invalid version "%s" in %s(). Must be a valid semver version or "@since next-version"',
                $parameters[$paramPosition - 1]['start'],
                'InvalidVersion',
                [$version, $functionName]
            );

            if ($fix === true) {
                return $this->fixVersionParameter($phpcsFile, $parameters[$paramPosition - 1]);
            }
        }
    }

    /**
     * Gets information about function parameters.
     *
     * @param File $phpcsFile The file being scanned.
     * @param int  $stackPtr  The position of the current token in the stack.
     *
     * @return array Array with parameter info.
     */
    private function getFunctionParameters(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $opener = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr);
        if ($opener === false) {
            return [];
        }

        if (!isset($tokens[$opener]['parenthesis_closer'])) {
            return [];
        }

        $closer = $tokens[$opener]['parenthesis_closer'];
        $parameters = [];
        $nestingLevel = 0;
        $currentParam = [
            'start' => null,
            'end' => null,
            'raw' => '',
        ];

        for ($i = $opener + 1; $i < $closer; $i++) {
            // Track nesting of parentheses
            if ($tokens[$i]['code'] === T_OPEN_PARENTHESIS) {
                $nestingLevel++;
            }
            if ($tokens[$i]['code'] === T_CLOSE_PARENTHESIS) {
                $nestingLevel--;
            }

            // Only process commas at the base nesting level
            if ($tokens[$i]['code'] === T_COMMA && $nestingLevel === 0) {
                $currentParam['end'] = $i - 1;
                $parameters[] = $currentParam;
                $currentParam = [
                    'start' => null,
                    'end' => null,
                    'raw' => '',
                ];
                continue;
            }

            if ($currentParam['start'] === null) {
                $currentParam['start'] = $i;
            }
            $currentParam['raw'] .= $tokens[$i]['content'];
        }

        // Add the last parameter
        if ($currentParam['start'] !== null) {
            $currentParam['end'] = $closer - 1;
            $parameters[] = $currentParam;
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
    private function isValidSemver($version)
    {
        try {
            $this->versionParser->normalize($version);
            return true;
        } catch (\UnexpectedValueException $e) {
            return false;
        }
    }

    private function fixVersionParameter(File $phpcsFile, array $parameter)
    {
        $tokens = $phpcsFile->getTokens();

        // Get the full parameter content
        $content = '';
        for ($i = $parameter['start']; $i <= $parameter['end']; $i++) {
            $content .= $tokens[$i]['content'];
        }

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($parameter['start'], "'@since next-version'");

        // Clear any remaining tokens
        for ($i = $parameter['start'] + 1; $i <= $parameter['end']; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->endChangeset();

        return true;
    }
}