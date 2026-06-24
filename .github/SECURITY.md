# Security Policy

The WPGraphQL team takes the security of WPGraphQL and the plugins in this
monorepo (WPGraphQL, WPGraphQL IDE, WPGraphQL Smart Cache, WPGraphQL for ACF,
and WPGraphQL Schema Monitor) seriously. Thank you for helping keep WPGraphQL
and its users safe.

## Supported Versions

Security fixes are released against the latest published version of each plugin
on WordPress.org and in this repository. We do not backport fixes to older
releases, so the most reliable way to stay protected is to run the latest
version and keep automatic updates enabled.

| Plugin | Supported |
| --- | --- |
| Latest released version | :white_check_mark: |
| Older versions | :x: |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues,
discussions, or pull requests.**

Instead, report them privately using either of the following:

- **Email:** [info@wpgraphql.com](mailto:info@wpgraphql.com)
- **GitHub:** open a private advisory via the repository's
  [Security Advisories](https://github.com/wp-graphql/wp-graphql/security/advisories/new)
  page (GitHub Private Vulnerability Reporting).

To help us triage and resolve the issue quickly, please include as much of the
following as you can:

- The plugin(s) and version(s) affected.
- The type of issue (e.g. authorization bypass, information disclosure,
  injection).
- Step-by-step instructions to reproduce the issue, including any required
  configuration, GraphQL queries, or proof-of-concept.
- The impact of the issue, including how an attacker might exploit it.

## Response Process

- We aim to acknowledge new reports within **5 business days**.
- We will work with you to understand and validate the issue, keep you updated
  on our progress, and let you know when a fix is released.
- Please give us a reasonable amount of time to release a fix before any public
  disclosure. We are happy to credit reporters in the release notes once a fix
  has shipped, unless you prefer to remain anonymous.
