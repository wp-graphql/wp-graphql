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

WPGraphQL is maintained by a very small team, and security reports are triaged in
batches rather than continuously. We would rather tell you what to expect than
promise a turnaround we cannot keep, so:

- **We do not commit to a fixed time for the first reply.** In practice it has
  ranged from the same day to a few months, depending on what else is in flight
  and how much of a backlog has built up.
- **We add you as a credit on the advisory** unless you ask us not to. GitHub
  then asks you to accept it, and your name appears on the published advisory
  only once you do, so it is worth accepting the invitation when it arrives.
- **If a report has gone quiet, nudge us.** Comment on the advisory thread, or
  email [info@wpgraphql.com](mailto:info@wpgraphql.com). That is genuinely
  useful rather than a nuisance, and it is the fastest way to move a report that
  has stalled.
- **If you are working to a disclosure deadline, tell us the date when you
  report.** We will tell you whether we can meet it rather than letting it pass
  quietly. Short of that, please give us a reasonable amount of time to release a
  fix before any public disclosure.

## What happens after you report

1. **Triage.** We reproduce the issue and tell you what we found, including the
   parts where we disagree with the report. The severity and CVSS score on the
   advisory are our assessment and may differ from the one you submitted.
2. **Fix.** The patch is developed in a temporary private fork attached to the
   advisory. If you reported through GitHub, or we have added you to the
   advisory, you have access to that fork, so you are welcome to review the fix
   before it ships, and we would rather hear that we got it wrong before release
   than after.
3. **Release.** The fix ships in a normal release to WordPress.org. If it changes
   behavior that existing code might rely on, the release notes and the plugin's
   upgrade notice say so.
4. **Publication.** The advisory is published once there is a version to update
   to, so at or shortly after the release. **Publishing closes the advisory's
   comment thread permanently**, and we have no way to reply after that point. We
   try to answer every open question before then, so please ask anything you want
   answered before publication.
5. **CVE.** Identifiers reach WPGraphQL advisories from two directions. GitHub is
   a CNA for its own advisory database, so a CVE can be requested through the
   advisory itself, and WordPress ecosystem databases such as WPScan and
   Patchstack pick up published advisories independently and sometimes assign
   their own. Either way the assignment is not ours to make, the timing has
   ranged from a few days to indefinitely, and plenty of advisories never receive
   one. When an ID is issued it is added to the advisory, so the advisory page is
   the place to check rather than asking us.

## Security acknowledgements

Some reports lead to hardening rather than to a patch, for example a change to a
default. Those do not get a published advisory, because an advisory tells you that
updating to a particular version fixes the problem, and a default only applies where
it is applied. The reporters below changed WPGraphQL without one. Reports that did
result in an advisory are credited on the advisory itself.

| Release | Reporter | What changed |
| --- | --- | --- |
| 2.24.0 | [@EQSTLab](https://github.com/EQSTLab), [@pnn219](https://github.com/pnn219) | Query depth limiting defaults on for new installs, introspection-only operations are exempt from it, and the settings review invites existing sites to turn it on. |
