# WPGraphQL Experiments API - Roadmap to Ship

**PR**: [#3098](https://github.com/wp-graphql/wp-graphql/pull/3098)  
**Status**: In Progress  
**Target**: v2.x (next minor release)

## Overview

The Experiments API enables WPGraphQL to ship, iterate, and gather feedback on potential core features without long-term compatibility commitments. This allows the community to "experience the future of WPGraphQL today" while maintaining a stable production API.

---

## Phase 1: Core API Completion (REQUIRED FOR MERGE)

### 1.1 Testing ✅ PRIORITY
**Owner**: TBD  
**Status**: ❌ Not Started  
**Blocker**: Yes

- [ ] **Unit Tests**
  - [ ] `ExperimentRegistry::register_experiments()` 
  - [ ] `ExperimentRegistry::get_experiments()`
  - [ ] `AbstractExperiment::is_enabled()` logic
  - [ ] Experiment slug uniqueness
  - [ ] `GRAPHQL_EXPERIMENTAL_FEATURES` constant behavior

- [ ] **Integration Tests**
  - [ ] Experiments Settings page renders correctly
  - [ ] Activation/deactivation persists to database
  - [ ] Experiment hooks fire correctly (`AbstractExperiment::init()`)
  - [ ] Multiple experiments can be active simultaneously

- [ ] **Deprecation Tests**
  - [ ] Deprecated experiments show admin notices
  - [ ] Deprecated experiments can be deactivated
  - [ ] Deprecation warnings appear in GraphQL responses (if applicable)

**Acceptance Criteria**: 
- All tests pass
- Code coverage for new code ≥ 80%
- CI/CD green

---

### 1.2 Code Cleanup ✅ REQUIRED
**Owner**: @justlevine  
**Status**: ❌ Not Started  
**Blocker**: Yes

- [ ] Remove `src/Experimental/Experiment/TestExperiment.php`
- [ ] Ensure no test/debug code remains
- [ ] Verify PHPStan types are finalized (unseal types mentioned in comments)

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
**Owner**: TBD  
**Status**: ❌ Not Started  
**Blocker**: Yes

- [ ] **Developer Documentation**
  - [ ] Update `src/Experimental/README.md` with final API
  - [ ] Add "Creating an Experiment" tutorial
  - [ ] Document experiment lifecycle (active → deprecated → removed)
  - [ ] Document dependency system with examples
  - [ ] Example experiment implementation

- [ ] **User Documentation**
  - [ ] Settings page help text
  - [ ] What are experiments? (user-facing explanation)
  - [ ] How to provide feedback on experiments
  - [ ] Explain dependency relationships in UI

- [ ] **Inline Code Documentation**
  - [ ] All public methods have complete PHPDoc blocks
  - [ ] Complex logic has explanatory comments
  - [ ] Dependency resolution logic is well-documented

**Acceptance Criteria**:
- A developer can create their first experiment using only the docs
- A developer understands how to declare dependencies
- A user understands what experiments are and how to enable them
- A user understands dependency relationships

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

| Phase | Estimated Effort | Target Date |
|-------|------------------|-------------|
| Phase 1: Core Completion | 2-3 weeks | TBD |
| Phase 2: UX Polish | 1 week | TBD |
| Phase 3: EmailAddress Experiment | 3-5 days | TBD |
| **SHIP v2.x** | - | TBD |
| Phase 4: Future Enhancements | Ongoing | v2.1+ |

---

## Open Questions

1. **GraphQL extensions visibility**: Should `extensions.experiments` show in all responses, or only in debug mode?
   - *Recommendation*: Always show - it's minimal overhead and useful for debugging production issues

2. **Experiment lifecycle policy**: How long can experiments stay active before graduating/deprecating?
   - *Recommendation*: Document a policy (e.g., "2-3 major versions max")

3. **Third-party experiments**: Should the API support external plugins registering experiments?
   - *Recommendation*: Not for v1. Core-only keeps scope tight.

4. **Admin notifications**: Are they MVP or v2.1+? 
   - *Recommendation*: Defer to v2.1 unless strong user demand

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

**Last Updated**: 2025-01-07  
**Document Owner**: TBD  
**Next Review**: After Phase 1 completion