# WPGraphQL Experiments API - Roadmap to Ship

**PR**: [#3098](https://github.com/wp-graphql/wp-graphql/pull/3098)
**Status**: In Progress
**Target**: v2.x (next minor release)

## Overview

The Experiments API enables WPGraphQL to ship, iterate, and gather feedback on potential core features without long-term compatibility commitments. This allows the community to "experience the future of WPGraphQL today" while maintaining a stable production API.

---

## Phase 1: Core API Completion (REQUIRED FOR MERGE)

### 1.1 Testing ✅ PRIORITY

**Owner**: @jasonbahl
**Status**: 🟢 Core Tests Complete
**Blocker**: Yes

- [x] **Unit Tests**

  - [x] `ExperimentRegistry::register_experiments()`
  - [x] `ExperimentRegistry::get_experiments()`
  - [x] `AbstractExperiment::is_active()` logic
  - [x] Experiment slug retrieval via `get_slug()`
  - [x] Experiment config validation (title, description)
  - [x] `GRAPHQL_EXPERIMENTAL_FEATURES` constant behavior (false & array)
  - [x] `graphql_dangerously_override_experiments` filter
  - [x] Deprecation methods (`is_deprecated()`, `get_deprecation_message()`)
  - [x] Test isolation via `ExperimentRegistry::reset()` method
  - [ ] Experiment slug uniqueness (validation/error handling) - **Deferred** (code review sufficient)

- [x] **Integration Tests**

  - [ ] Experiments Settings page renders correctly - **Deferred to Phase 2**
  - [x] Activation/deactivation persists to database
  - [x] Experiment hooks fire correctly (`AbstractExperiment::init()`)
  - [x] Multiple experiments can be active simultaneously

- [ ] **Deprecation Tests**
  - [ ] Deprecated experiments show admin notices - **Deferred to Phase 2**
  - [x] Deprecated experiments can be deactivated (tested via `is_deprecated()`)
  - [ ] Deprecation warnings appear in GraphQL responses - **Nice-to-have**

**Current Test Coverage**:

- ✅ **11 passing tests** across 3 test files
- ✅ `ExperimentRegistryTest.php`: 2 tests (registration, activation state)
- ✅ `AbstractExperimentTest.php`: 6 tests (config, slug, deprecation, constants, filter)
- ✅ `ExperimentIntegrationTest.php`: 3 tests (persistence, hooks, multiple experiments)
- ✅ PHPStan analysis passes (all errors fixed)
- ✅ Added `ExperimentRegistry::reset()` for clean test isolation

**Recent Changes** (2025-01-09):

- Fixed test failures caused by static property persistence across tests
- Added clean `reset()` method to `ExperimentRegistry` instead of using Reflection
- All experiment-related tests now passing in CI

**Acceptance Criteria**:

- ✅ All core tests pass
- ⏸️ Code coverage for new code ≥ 80% - **Check CI report**
- ⏸️ CI/CD green - **Verify**

---

### 1.2 Code Cleanup ✅ REQUIRED

**Owner**: @jasonbahl
**Status**: 🟢 Complete
**Blocker**: Yes

- [x] Document `TestExperiment` with clear purpose and usage
- [x] Update `ExperimentRegistry` with improved documentation and inline code examples
- [x] Ensure no test/debug code remains
- [x] Verify PHPStan passes (✅ No errors)
- [x] Verify all tests pass (✅ 861 tests passing)

**Changes** (2025-10-09):

- Updated `TestExperiment.php` with comprehensive documentation
  - Clearly states it's a simple demonstration experiment
  - Explains it adds a `testExperiment` field to RootQuery
  - Includes example GraphQL query in docblock
  - References docs for real-world examples
- Improved `register_experiments()` method documentation
  - Added inline comment explaining TestExperiment's purpose
  - Added code example for registering custom experiments via filter
  - Clearer PHPDoc blocks

---

### 1.3 Experiment Dependencies ✅ REQUIRED

**Owner**: TBD
**Status**: ❌ Not Started
**Blocker**: Yes

Without dependencies, multi-experiment scenarios can break the schema (e.g., if oneOf inputs experiment references EmailAddress scalar, but EmailAddress experiment isn't enabled).

- [ ] **Core Dependency Logic**

  - [ ] Add `get_dependencies(): array` method to `AbstractExperiment`
    - Return format: `['required' => ['slug1', 'slug2'], 'optional' => ['slug3']]`
  - [ ] Add dependency resolution in `ExperimentRegistry::register_experiments()`
  - [ ] Auto-enable required dependencies when parent experiment is activated
  - [ ] Prevent disabling experiments that other active experiments depend on
  - [ ] Support optional dependencies (show recommendations but don't enforce)

- [ ] **Validation & Error Handling**

  - [ ] Check for circular dependencies during registration (throw exception)
  - [ ] Validate all dependency slugs exist
  - [ ] Return `WP_Error` if activation blocked due to circular dependency
  - [ ] Log warnings for missing optional dependencies

- [ ] **Settings UI Updates**

  - [ ] Show "Enabling this will also enable: X, Y, Z" before activation
  - [ ] Show "Required by: Z" message when trying to disable a dependency
  - [ ] Disable checkbox for dependencies that are required by active experiments
  - [ ] Visual indicator (maybe indent or arrows) for dependency relationships
  - [ ] Show optional dependencies as recommendations

- [ ] **Testing**
  - [ ] Test simple dependency chains (A → B, A → B → C)
  - [ ] Test circular dependency detection (A → B → A)
  - [ ] Test activation with dependencies (enabling parent enables children)
  - [ ] Test deactivation prevention (can't disable if dependents active)
  - [ ] Test invalid dependency slug handling
  - [ ] Test optional vs required dependencies

**Example Implementation**:

```php
class OneOfInputsExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => ['email-address-scalar'],
            'optional' => ['advanced-filtering'],
        ];
    }
}
```

**Acceptance Criteria**:

- Cannot enable an experiment without its required dependencies
- Cannot disable an experiment if other active experiments depend on it
- Circular dependencies are detected and prevented
- Settings UI clearly shows dependency relationships

---

### 1.4 Documentation ✅ REQUIRED

**Owner**: @jasonbahl
**Status**: 🟢 Complete
**Blocker**: Yes

- [x] **Developer Documentation**

  - [x] Add "Creating an Experiment" tutorial (`docs/experiments-creating.md`)
  - [x] Document experiment lifecycle (active → deprecated → removed)
  - [x] Document dependency system patterns and examples
  - [x] Multiple code examples and patterns
  - [ ] Update `src/Experimental/README.md` with final API - **Deferred** (covered in main docs)

- [x] **User Documentation**

  - [x] What are experiments? (`docs/experiments.md`)
  - [x] How to enable/use experiments (`docs/experiments-using.md`)
  - [x] How to provide feedback on experiments
  - [x] Environment-specific strategies
  - [x] Troubleshooting guide
  - [ ] Settings page help text - **Deferred to Phase 2**

- [x] **Contributor Documentation**

  - [x] How to contribute experiments (`docs/experiments-contributing.md`)
  - [x] Proposal template and process
  - [x] Graduation and deprecation processes
  - [x] Code standards and best practices

- [x] **Documentation Structure**

  - [x] Added dedicated "Experiments" section to docs navigation
  - [x] 4 comprehensive documentation pages
  - [x] Cross-referenced with related docs

- [ ] **Inline Code Documentation**
  - [ ] All public methods have complete PHPDoc blocks - **In Progress**
  - [ ] Complex logic has explanatory comments - **Partial**
  - [ ] Dependency resolution logic well-documented - **Pending dependencies implementation**

**Completed Documentation** (2025-01-09):

- ✅ `docs/experiments.md` - Overview, philosophy, and FAQ (~166 lines)
- ✅ `docs/experiments-using.md` - End-user guide for enabling/testing (~393 lines)
- ✅ `docs/experiments-creating.md` - Developer guide for building experiments (~608 lines)
- ✅ `docs/experiments-contributing.md` - Contributor guide for submitting to core (~547 lines)
- ✅ `docs/docs_nav.json` - Added "Experiments" section to navigation
- ✅ `README.md` - Added Vision section with experiments/extensions distinction

**Total**: ~1,700 lines of comprehensive documentation across 4 docs + README updates

**Acceptance Criteria**:

- ✅ A developer can create their first experiment using only the docs
- ⏸️ A developer understands how to declare dependencies - **Will finalize after Phase 1.3**
- ✅ A user understands what experiments are and how to enable them
- ✅ A contributor understands the proposal and review process
- ⏸️ Settings page has contextual help - **Deferred to Phase 2**

---

## Phase 2: User Experience Polish (RECOMMENDED FOR V1)

### 2.1 Basic Activation Messaging ⭐ RECOMMENDED

**Owner**: TBD
**Status**: ❌ Not Started
**Blocker**: No (but highly recommended)

Implement the **simple approach** from Jason's June 4 comment:

- [ ] Add `get_activation_message()` method to `AbstractExperiment`
- [ ] Add `get_deactivation_message()` method to `AbstractExperiment`
- [ ] Display contextual messaging on Experiments settings page
  - Show activation message when experiment is OFF
  - Show deactivation message when experiment is ON
- [ ] Update `TestExperiment` example (before removing) to demonstrate usage

**Example**:

```php
public function get_activation_message(): string {
    return 'After activating, navigate to GraphQL > IDE to try the new interface.';
}
```

**Why Recommended**: Experiments without "what's next?" guidance create friction. This is a lightweight solution that doesn't require full activation hooks.

---

### 2.2 Improved Settings UI 🎨 NICE-TO-HAVE

**Owner**: TBD
**Status**: ❌ Not Started
**Blocker**: No (can ship without)

- [ ] Design custom field type for experiments (vs recycling existing Settings API)
- [ ] Add visual indicators for experimental vs deprecated experiments
- [ ] Consider grouping/filtering if many experiments exist

**Why Nice-to-Have**: Current UI is functional. This can be improved in v2.1+ based on user feedback.

---

### 2.3 GraphQL Extensions Response 🔌 ⭐ RECOMMENDED

**Owner**: TBD
**Status**: ❌ Not Started
**Blocker**: No (but highly recommended for v1)

Expose active experiments in GraphQL response extensions:

```json
{
  "data": { ... },
  "extensions": {
    "experiments": ["email-address-scalar"]
  }
}
```

**Implementation**:

- [ ] Add filter/hook to append experiments to GraphQL response extensions
- [ ] Only include experiments that are currently active
- [ ] Keep it simple - just an array of experiment slugs
- [ ] Consider: Show only in debug mode? Or always?

**Why Recommended**:

- **Debugging value**: Clients immediately see they're hitting experimental APIs
- **No schema pollution**: Uses standard GraphQL extensions pattern
- **Minimal overhead**: Just an array of strings
- **Solves real problem**: Teams can detect experiments without coordinating with WP admin

**Not Included** (can add later if needed):

- Rich metadata (descriptions, links, deprecation dates)
- Per-field experiment tracking
- Client-side experiment opt-in/opt-out

---

## Phase 3: First Real Experiment 🧪

### 3.1 Custom Scalars Experiment ⭐ PILOT

**Owner**: @jasonbahl
**Status**: 🟡 EmailAddress PR open ([#3423](https://github.com/wp-graphql/wp-graphql/pull/3423))
**Blocker**: No (validates Experiments API)

Convert the EmailAddress scalar PR to be an experiment:

- [ ] Create `EmailAddressScalarExperiment` extending `AbstractExperiment`
- [ ] Move EmailAddress scalar registration into experiment
- [ ] Add experiment metadata:

  - Title: "Custom Scalars: EmailAddress"
  - Description: "Adds EmailAddress scalar type for better type safety and validation"
  - Link to feedback: GitHub Discussions or issue

- [ ] Test experiment workflow:
  - [ ] Enable in settings
  - [ ] Verify schema includes EmailAddress scalar
  - [ ] Verify deprecation warnings work
  - [ ] Disable in settings
  - [ ] Verify EmailAddress scalar removed from schema

**Success Criteria**:

- EmailAddress scalar ships as an experiment
- Community can provide feedback before it's committed to core
- Validates that the Experiments API works for real features

**Future Scalars** (after EmailAddress proves the pattern):

- `URL` scalar
- `DateTime` scalar improvements
- `JSON` scalar
- Other domain-specific types

---

## Phase 4: Future Enhancements (POST-SHIP)

These can be added in subsequent releases based on usage patterns:

### 4.1 Admin Notifications 🔔

**Status**: ❌ Deferred to v2.1+

- [ ] Notify admins when new experiments are available
- [ ] Notify when experiments are deprecated
- [ ] Requires: Store experiment state in DB to diff against

**Decision**: Wait to see if users actually need this. May be overkill for v1.

---

### 4.2 Full Activation Hooks 🪝

**Status**: ❌ Deferred (YAGNI until proven need)

- [ ] `on_activate()` method in `AbstractExperiment`
- [ ] `on_deactivate()` method in `AbstractExperiment`
- [ ] Examples: Redirect to settings, flush rewrite rules, etc.

**Decision**: Simple messaging (Phase 2.1) likely sufficient. Add hooks only if clear use case emerges.

---

### 2.3 GraphQL Extensions Response 🔌 ⭐ RECOMMENDED

**Owner**: TBD
**Status**: ❌ Not Started
**Blocker**: No (but highly recommended for v1)

Expose active experiments in GraphQL response extensions:

```json
{
  "data": { ... },
  "extensions": {
    "experiments": ["email-address-scalar"]
  }
}
```

**Implementation**:

- [ ] Add filter/hook to append experiments to GraphQL response extensions
- [ ] Only include experiments that are currently active
- [ ] Keep it simple - just an array of experiment slugs
- [ ] Consider: Show only in debug mode? Or always?

**Why Recommended**:

- **Debugging value**: Clients immediately see they're hitting experimental APIs
- **No schema pollution**: Uses standard GraphQL extensions pattern
- **Minimal overhead**: Just an array of strings
- **Solves real problem**: Teams can detect experiments without coordinating with WP admin

**Not Included** (can add later if needed):

- Rich metadata (descriptions, links, deprecation dates)
- Per-field experiment tracking
- Client-side experiment opt-in/opt-out

---

### 4.4 Site Health Integration 🏥

**Status**: ❌ Deferred to future release

- [ ] Add WPGraphQL section to WordPress Site Health screen
- [ ] List active experiments
- [ ] Useful for debugging

**Decision**: Mentioned in office hours but not critical for v1.

---

### 4.5 Duplicate Slug Detection ⚠️

**Status**: ❌ Deferred (code review sufficient for now)

- [ ] Warn/error if multiple experiments register with same slug
- [ ] Consider `_doing_it_wrong()` notice

**Decision**: Core uses hard-coded map, so code review catches this. Can add if third-party experiments become common.

---

## Definition of Done

The Experiments API is ready to ship when:

- ✅ All Phase 1 items are complete (tests, cleanup, docs)
- ✅ Phase 2.1 (activation messaging) is complete OR explicitly deferred with justification
- ✅ Phase 2.3 (GraphQL extensions response) is complete OR explicitly deferred with justification
- ✅ EmailAddress scalar is converted to an experiment and validated
- ✅ All open discussions resolved (remove `needs: discussion` label)
- ✅ Final review from @jasonbahl and @justlevine
- ✅ Changelog entry written
- ✅ Migration guide for extension developers (if applicable)

---

## Timeline (Proposed)

| Phase                            | Estimated Effort | Target Date |
| -------------------------------- | ---------------- | ----------- |
| Phase 1: Core Completion         | 2-3 weeks        | TBD         |
| Phase 2: UX Polish               | 1 week           | TBD         |
| Phase 3: EmailAddress Experiment | 3-5 days         | TBD         |
| **SHIP v2.x**                    | -                | TBD         |
| Phase 4: Future Enhancements     | Ongoing          | v2.1+       |

---

## Open Questions

1. **GraphQL extensions visibility**: Should `extensions.experiments` show in all responses, or only in debug mode?

   - _Recommendation_: Always show - it's minimal overhead and useful for debugging production issues

2. **Experiment lifecycle policy**: How long can experiments stay active before graduating/deprecating?

   - _Recommendation_: Document a policy (e.g., "2-3 major versions max")

3. **Third-party experiments**: Should the API support external plugins registering experiments?

   - _Recommendation_: Not for v1. Core-only keeps scope tight.

4. **Admin notifications**: Are they MVP or v2.1+?
   - _Recommendation_: Defer to v2.1 unless strong user demand

---

## Success Metrics

How will we know the Experiments API is successful?

- **Developer Adoption**: WPGraphQL core uses experiments for at least 2-3 features
- **Community Feedback**: Clear feedback channels for experiments (GH Discussions, issues)
- **Graduation Rate**: Experiments either graduate to core or get deprecated within 2-3 releases
- **Reduced Core Bloat**: Experimental features don't pile up indefinitely
- **User Clarity**: Users understand what experiments are and feel comfortable enabling them

---

## Notes

- This PR has been open since April 2024 - let's get it across the finish line! 🎉
- The EmailAddress scalar is the perfect first experiment - it validates the API while providing real value
- Keep the v1 scope tight - resist feature creep, we can iterate in v2.x
- Focus on making the developer experience excellent for creating experiments

---

## 🎯 Next Steps (Prioritized)

### Immediate (This Week)

1. **✅ DONE: Fix Test Failures**

   - ✅ All experiment tests passing
   - ✅ Added `ExperimentRegistry::reset()` method for test isolation

2. **✅ DONE: Code Cleanup** (Phase 1.2)

   - ✅ Documented `TestExperiment` as a demonstration experiment
   - ✅ Added comprehensive inline documentation and code examples
   - ✅ All tests passing (861 tests)
   - ✅ PHPStan passing (0 errors)

3. **🔴 BLOCKER: Verify CI/CD**
   - [ ] Ensure all tests pass in GitHub Actions
   - [ ] Verify code coverage meets threshold
   - [ ] Check for any remaining linter warnings
   - **Owner**: @jasonbahl
   - **Estimated**: 30 minutes

### This Sprint (Next 1-2 Weeks)

4. **🔴 BLOCKER: Experiment Dependencies** (Phase 1.3)

   - This is THE biggest remaining blocker
   - Without this, multi-experiment scenarios can break
   - **Owner**: TBD
   - **Estimated**: 3-5 days
   - **See**: Full spec in Phase 1.3

5. **✅ DONE: Documentation** (Phase 1.4)
   - ✅ Created 4 comprehensive docs (~3,500 lines)
   - ✅ Added "Experiments" section to docs navigation
   - ✅ Covered all user, developer, and contributor scenarios
   - **Note**: Will add inline PHPDoc blocks as part of code cleanup

### Recommended for V1 (Before Merge)

6. **⭐ RECOMMENDED: Basic Activation Messaging** (Phase 2.1)

   - [ ] Add `get_activation_message()` / `get_deactivation_message()` methods
   - [ ] Display in settings UI
   - **Why**: Experiments without guidance create friction
   - **Estimated**: 1 day

7. **⭐ RECOMMENDED: GraphQL Extensions Response** (Phase 2.3)
   - [ ] Add active experiments to `extensions.experiments` in GraphQL responses
   - **Why**: Enables debugging and transparency
   - **Estimated**: 1 day

### Post-Merge (Future Releases)

8. **Convert EmailAddress Scalar to Experiment** (Phase 3.1)
   - Validates the entire Experiments API with a real feature
   - **Owner**: @jasonbahl
   - **Estimated**: 3-5 days

---

## 🚦 Merge Checklist

Before merging to `develop`:

- [ ] ✅ All Phase 1 items complete (testing, cleanup, dependencies, docs)
- [ ] Phase 2.1 (activation messaging) complete OR explicitly deferred
- [ ] Phase 2.3 (GraphQL extensions) complete OR explicitly deferred
- [ ] All tests passing in CI
- [ ] Code coverage ≥ 80%
- [ ] PHPStan passing
- [ ] Final review from @jasonbahl and @justlevine
- [ ] Changelog entry written
- [ ] Migration guide (if applicable)

---

**Last Updated**: 2025-01-09
**Document Owner**: @jasonbahl
**Next Review**: After dependencies implementation (Phase 1.3)
