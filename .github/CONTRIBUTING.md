## Contribute to WPGraphQL

WPGraphQL welcomes community contributions, bug reports and other constructive feedback.

When contributing please ensure you follow the guidelines below so that we can keep on top of things.

> **📚 Full Contributing Guide**: For comprehensive documentation on contributing, including development setup, testing, and the release process, see [docs/CONTRIBUTING.md](../docs/CONTRIBUTING.md).

## Getting Started

* __Do not report potential security vulnerabilities here. Email them privately to our security team at 
[info@wpgraphql.com](mailto:info@wpgraphql.com)__
* Before submitting a ticket, please be sure to replicate the behavior with no other plugins active and on a base theme like Twenty Seventeen.
* Submit a ticket for your issue, assuming one does not already exist.
  * Raise it on our [Issue Tracker](https://github.com/wp-graphql/wp-graphql/issues)
  * Clearly describe the issue including steps to reproduce the bug.
  * Make sure you fill in the earliest version that you know has the issue as well as the version of WordPress you're using.

## Making Changes

* Fork the repository on GitHub
* Make the changes to your forked repository
  * Ensure you stick to the [WordPress Coding Standards](https://codex.wordpress.org/WordPress_Coding_Standards)
* When committing, reference your issue (if present) and include a note about the fix
* If possible, and if applicable, please also add/update unit tests for your changes
  * **Code Coverage**: PRs must maintain or increase code coverage (both project-wide and for new code). While we encourage contributors to include tests, maintainers can help fulfill testing requirements if needed.
* Push the changes to your fork and submit a pull request to the `main` branch of this repository

## Code Documentation

* We strive for full doc coverage and follow the standards set by phpDoc
* Please make sure that every function is documented so that when we update our API Documentation things don't go awry!
* Finally, please use tabs and not spaces.

At this point you're waiting on us to merge your pull request. We'll review all pull requests, and make suggestions and changes if necessary.

## Issue Triage

We use a single GitHub Project Board to manage issue triage across the WPGraphQL organization. The board provides various filtered views to help with different aspects of the triage process.

**Main Project Board**: [WPGraphQL Issue Triage](https://github.com/orgs/wp-graphql/projects/11)

The project board includes the following filtered views to help with triage:

- **[Issue Triage](https://github.com/orgs/wp-graphql/projects/11/views/7)** - Initial triage of new issues, including labeling with appropriate labels such as:
  - **:rocket: Actionable**: Issues with enough detail for someone to create a Pull Request
  - **Needs Discussion**: Issues that need more detail or discussion before they can be actionable
  - **Question**: Questions that need to be answered but won't lead to a pull request
- **[Close Candidates](https://github.com/orgs/wp-graphql/projects/11/views/8)** - Issues that may be ready to close
- **[Questions](https://github.com/orgs/wp-graphql/projects/11/views/9)** - Issues labeled as questions that need responses
- **[Needs Response](https://github.com/orgs/wp-graphql/projects/11/views/21)** - Issues awaiting responses from issue creators
- **[Needs Reproduction](https://github.com/orgs/wp-graphql/projects/11/views/22)** - Issues that need reproduction steps or examples
- **[Bugs](https://github.com/orgs/wp-graphql/projects/11/views/23)** - Issues identified as bugs
- **[Issues by Scope](https://github.com/orgs/wp-graphql/projects/11/views/13)** - Issues organized by their scope
- **[Issues by Priority](https://github.com/orgs/wp-graphql/projects/11/views/11)** - Issues organized by priority level

## Automated Release Processes

The following are handled automatically by our CI/CD workflows. **Please do not manually edit these**:

| What | Where | Automated By |
|------|-------|--------------|
| Version numbers | `constants.php`, `wp-graphql.php`, `package.json`, `readme.txt` | release-please |
| `@since` tags | PHP files | release-please (replaces `x-release-please-version`) |
| Changelog entries | `CHANGELOG.md` | release-please |
| **Upgrade Notice** | `readme.txt` | `update-release-pr.yml` workflow |

### Breaking Changes & Upgrade Notices

When a release contains breaking changes (commits with `feat!:`, `fix!:`, or `perf!:` prefix):

1. **release-please** detects the breaking changes and adds them to `CHANGELOG.md`
2. **update-release-pr.yml** workflow automatically updates the `== Upgrade Notice ==` section in `readme.txt`
3. WordPress.org displays this notice to users before they update

**⚠️ Do not manually add upgrade notices** - they will be automatically generated from breaking changes in the changelog.

> **NOTE:** This CONTRIBUTING.md file was forked from [Easy Digital Downloads](https://github.com/easydigitaldownloads/easy-digital-downloads/blob/main/CONTRIBUTING.md)
