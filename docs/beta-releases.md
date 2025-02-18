# Beta Releases

WPGraphQL uses beta releases to test major changes and breaking features before they are released to the public.

> **Note**: Beta releases are automated via GitHub Actions. See [GitHub Workflows](../.github/workflows/README.md) and [Changesets](../.changeset/README.md) for technical details.

## Branch Strategy

Beta releases are developed on the `next-major` branch:
- Breaking changes and major features target this branch
- Breaking changes should use the `!` suffix in PR titles (e.g., `feat!:`)
- Regular features and fixes continue to target `develop`
- Beta releases are not deployed to WordPress.org

## Beta Release Process

### Automated Process
The beta release process is automated via GitHub Actions:
1. PRs with breaking changes target the `next-major` branch
2. When changes are merged:
   - Version is automatically bumped with beta suffix
   - Changelog is updated
   - GitHub pre-release is created
   - Stable tag in readme.txt remains unchanged

### Changeset Generation
Breaking changes are tracked through changesets, which are automatically generated when PRs are merged. To indicate a breaking change:

1. Add `!` suffix to your PR title: `feat!: breaking change`
2. Document breaking changes in PR description
3. Include upgrade instructions in PR description

The automation will handle:
- Version bumping
- Changelog updates
- @since tag updates

### Manual Steps
Some aspects require manual review:
1. Before merging PRs:
   - Ensure breaking changes are properly documented
   - Verify upgrade instructions are included
   - Check `@since todo` tags are present
2. Before releasing:
   - Review changelog entries
   - Verify version numbers
   - Test beta release

## Version Numbering
- Beta releases: `v3.0.0-beta.1`, `v3.0.0-beta.2`, etc.
- Final release: `v3.0.0`

## Testing Beta Releases

Beta releases can be tested by:
1. Installing from the GitHub release assets
2. Using Composer with the specific beta version:
   ```json
   {
     "require": {
       "wp-graphql/wp-graphql": "3.0.0-beta.1"
     }
   }
   ```
3. Cloning the repository and checking out the beta tag

## Providing Feedback

Feedback on beta releases can be provided through:
1. GitHub Issues
2. The beta release PR discussion
3. WPGraphQL Discord

## Notes
- Beta releases are marked as pre-releases on GitHub
- The stable tag in readme.txt is not updated for beta releases
- Breaking changes must include upgrade notes
- Beta releases are not deployed to WordPress.org