# Labels

This document describes the labels we use for the main WPGraphQL repository and can be used as a reference for other repositories.

## Global Labels

- `#ffffff` wontfix - *This will not be worked on*
- `#fcbfe3` good first issue - *Issue that doesn't require previous experience with codebase*
- `#e03590` close candidate - *Needs confirmation before closing*
- `#adb5bd` duplicate - *This issue or pull request already exists* (Changed from gray to a lighter gray for better visibility)
- `#6c757d` invalid? - *Something is not right here* (Changed to a darker gray to distinguish from other grays)
- `#ced4da` stale? - *May need to be revalidated due to prolonged inactivity* (Changed to an even lighter gray to differentiate within grays)
- `#d93f0b` regression - *Bug that causes a regression to a previously working feature*
- `#0e8a16` help wanted - *Issue with a clear description that the community can help with*

## Has

- `#adb5bd` has: workaround - *A temporary workaround has been provided* (Changed to a uniform gray to match other informational labels)

## Compatibility

- `#c82333` compat: breaking change - *This is a breaking change to existing functionality* (Brightened red for urgency)
- `#e0a800` compat: possible break - *There is a possibility that this might lead to breaking changes, but not confirmed yet* (Changed from a muted red to a clear yellow for caution)

## Level of Effort

- `#dc3545` effort: high - *More than a week* (Brightened to a more urgent red)
- `#ffc107` effort: med - *Less than a week* (Changed to a standard yellow for moderate effort)
- `#28a745` effort: low - *Around a day or less* (Standard green for low effort)

## Level of Impact

- `#28a745` impact: high - *Unblocks new use cases, substantial improvement to existing feature, fixes a major bug* (Uniform green for positive impact)
- `#ffc107` impact: med - *Minor performance improvements, fix broad user base issues* (Yellow for moderate impact)
- `#f8d7da` impact: low - *Fixes a minor issue for some people, slight DX improvement* (Soft pink for less critical improvements)

# Language

- `#45229e` lang: php - *Pull requests that update PHP code*
- `#168799` lang: javascript - *Pull requests that update JavaScript code*

## Needs Something

- `#6c757d` needs: discussion - *Requires a discussion to proceed* (Changed to a darker gray for visibility and importance)
- `#adb5bd` needs: info - *More information is needed to resolve this issue* (Uniform gray for informational needs)
- `#ced4da` needs: reproduction - *This issue needs to be reproduced independently* (Lighter gray to distinguish from more urgent needs)
- `#f2994a` needs: reviewer response - *This needs the attention of a codeowner or maintainer*
- `#56ccf2` needs: author response - *Pending information from the author*
- `#adb5bd` needs: tests - *Tests should be added to be sure this works as expected* (Uniform gray for consistency)

## Issue Scope

- `#b0c8d9` scope: build scripts - *Automating task runners and compilation processes*
- `#b8a8d6` scope: code quality - *Refactoring, linting, and enforcing coding standards*
- `#a8d2a0` scope: dependencies - *Managing, updating, or removing dependencies*
- `#ded6b0` scope: docs - *Updating, correcting, and improving documentation*
- `#a8d6d4` scope: extensions - *Integrating plugins, add-ons, or other extensions*
- `#ded3a0` scope: i18n - *Internationalizing, translating, and localizing*
- `#a8dbd4` scope: performance - *Enhancing speed and efficiency*
- `#deb5a0` scope: security - *Securing against vulnerabilities and threats*
- `#d3b8d6` scope: tests - *Developing unit tests, integration tests, and ensuring coverage*
- `#5ac5c5` scope: accessibility - *Enhancing accessibility and ensuring compliance with WCAG/ADA standards*
- `#b8a6d9` scope: graphiql - *Issues related to the GraphiQL interface enhancements or issues.*

## Statuses

- `#28a745` status: actionable - *Ready for work to begin* (Reverted to green for intuitive 'go' signaling)
- `#dc3545` status: blocked - *Progress halted due to dependencies or issues* (Bright red for critical blockage)
- `#fd7e14` status: in progress - *Currently being worked on* (Changed to a vibrant orange for visibility)
- `#17a2b8` status: in review - *Awaiting review before merging or closing* (Changed to a soothing blue to indicate ongoing review)

## Issue Type

- `#d73a4a` type: bug - *Issue that causes incorrect or unexpected behavior*
- `#fff1bc` type: chore - *Maintenance tasks, refactoring, and other non-functional changes*
- `#84b6eb` type: enhancement - *Improvements to existing functionality*
- `#f9ab45` type: feature - *New functionality being added*
- `#cc317c` type: question - *An issue that involve inquiries, clarifications, or requests for guidance*
- `#bfdadc` type: spike - *An issue that needs more research before becoming actionable*
- `#e88bdd` type: release - *Pull request intended for a release*
- `#005d5d` type: epic - *Large-scale feature or initiative that spans multiple issues and pull requests.*

## Dependencies

These should use the associated branding color

- `#7f54b3` dep: woocommerce - *Integration or compatibility with WooCommerce*
- `#c9471f` dep: wpml - *Related to WPML for multilingual support*
- `#b2fe80` dep: acf - *Involving Advanced Custom Fields functionality*
- `#a8c0d6` dep: polylang - *Integrating Polylang for multilingual support*

## Components

Specific components of the application

- `#fce6c4` component: ________ - *Relating to ________*

## Repo Specific (for now)

### wp-graphql

- `#59f7ad` not stale - *Short circuit stalebot. USE SPARINGLY.*
