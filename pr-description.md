Fixes #3513

## Problem
When calling `nodeByUri` with a REST API URI (e.g., `/wp-json/wp/v2/users`), the GraphQL response was returning the REST API JSON response instead of the expected `null` value.

## Solution
- Added early check in `resolve_uri()` to detect REST API URIs and return `null` before processing
- Added check for static file paths in uploads directory (e.g., `/wp-content/uploads/image.jpg`)
- Uses `rest_get_url_prefix()` to respect the `rest_url_prefix` filter for custom REST API prefixes
- Normalizes URIs to handle absolute URLs and subdirectory installs correctly
- Added proper boundary checking to prevent false positives (e.g., `/wp-json-foo` should not match)

## Testing
- Added `testRestApiUriReturnsNull()` to verify REST API URIs return null (relative, absolute, with query strings, fragments, trailing slashes, multiple slashes)
- Added `testRestApiUriWithCustomPrefixReturnsNull()` to verify custom REST API prefixes work
- Added `testStaticFilePathsReturnNull()` to verify static file paths return null
- Added `testMediaItemPermalinkResolvesButFilePathDoesNot()` to verify MediaItem permalinks still work while file paths don't
- Added comprehensive boundary tests to prevent false positives
- All existing tests pass
- All wpunit tests pass (970 tests, 6437 assertions)

## Edge Cases Covered
- ✅ Absolute vs relative URLs
- ✅ Subdirectory installs (home path stripping)
- ✅ Trailing slashes (`/wp-json/` vs `/wp-json`)
- ✅ Query strings (`/wp-json?foo=bar`)
- ✅ URL fragments (`/wp-json#something`)
- ✅ Multiple slashes (`/wp-json//users`)
- ✅ Custom REST API prefix via filter
- ✅ MediaItem permalinks vs file paths
- ✅ Boundary cases (false positive prevention)

## Code Quality
- ✅ PHPCS passes
- ✅ PHPStan passes (level 8)
- ✅ All tests pass (43 tests in NodeByUriTest, 312 assertions)
