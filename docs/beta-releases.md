# Beta Releases

WPGraphQL uses beta releases to test major changes and breaking features before they are released to the public.

## Branch Strategy

Beta releases are developed on the `next-major` branch:
- Breaking changes and major features target this branch
- Breaking changes should use the `!` suffix in PR titles (e.g., `feat!:`)
- Regular features and fixes continue to target `develop`
- Beta releases are not deployed to WordPress.org

## Creating Beta Releases

### Starting a Beta Cycle

```bash
# Switch to next-major branch
git checkout next-major

# Enter pre-release mode
npm run changeset pre enter beta

# Create first beta release
npm run version
git push --follow-tags
```

### Creating Additional Beta Releases

```bash
# Ensure you're on next-major
git checkout next-major

# Create next beta release
npm run version
git push --follow-tags
```

### Promoting to Stable

```bash
# Exit pre-release mode
npm run changeset pre exit

# Create stable release
npm run version
git push --follow-tags

# Merge to develop
git checkout develop
git merge next-major
```

## Version Numbering
- Beta releases: `v3.0.0-beta.1`, `v3.0.0-beta.2`, etc.
- Final release: `v3.0.0`

## Testing Beta Releases

Beta releases can be tested by:
1. Installing from the GitHub release assets
2. Using Composer with the specific beta version
3. Cloning the repository and checking out the beta tag

## Providing Feedback

Feedback on beta releases can be provided through:
1. GitHub Issues
2. The beta release PR discussion
3. WPGraphQL Discord

## Notes
- The stable tag in readme.txt is not updated for beta releases
- Beta releases are marked as pre-releases on GitHub
- Breaking changes should include upgrade notes