# EmailAddress Scalar Experiments - Refactoring Summary

## Overview
Successfully refactored the EmailAddress Scalar feature (PR #3423) into two opt-in experiments using the WPGraphQL Experiments API.

## Experiments Created

### 1. `email-address-scalar`
- **Location**: `src/Experimental/Experiment/EmailAddressScalarExperiment/`
- **Purpose**: Registers the `EmailAddress` GraphQL scalar type
- **Features**:
  - Custom scalar with email validation using WordPress's `is_email()`
  - Email sanitization using WordPress's `sanitize_email()`
  - Comprehensive error messages for invalid emails
- **Tests**: `tests/wpunit/experiments/email-address-scalar/EmailAddressTest.php`
- **Status**: ✅ All 5 tests passing

### 2. `email-address-scalar-fields`
- **Location**: `src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/`
- **Purpose**: Adds `emailAddress` fields to core types and mutations
- **Dependencies**: Requires `email-address-scalar` experiment
- **Features**:
  - Adds `Commenter.emailAddress` field (inherited by User and CommentAuthor)
  - Adds `GeneralSettings.adminEmail` field
  - Adds `CommentToCommenterConnectionEdge.emailAddress` field
  - Adds `emailAddress` input to user mutations (createUser, updateUser, registerUser)
  - Deprecates old `email` String fields with warnings
  - Backward compatible - old `email` fields continue to work
- **Tests**: `tests/wpunit/experiments/email-address-scalar-fields/UserEmailAddressFieldsTest.php`
- **Status**: ✅ All 7 tests passing

## Technical Implementation

### Key Architecture Decisions

1. **Interface-based Field Registration**
   - Registered `emailAddress` only on `Commenter` interface
   - `User` and `CommentAuthor` automatically inherit the field
   - Avoids duplicate field registration errors

2. **Input Normalization via Filter**
   - Used `graphql_mutation_input` filter from `WPMutationType`
   - Normalizes `emailAddress` → `email` before mutations execute
   - Validates that both fields aren't provided simultaneously
   - Logs deprecation warnings when old `email` field is used

3. **Deprecation Strategy**
   - Used `add_filter` on field definitions instead of re-registering
   - Applied to: `graphql_Commenter_fields`, `graphql_CommentAuthor_fields`, etc.
   - Wrapped resolvers to add deprecation warnings in debug output
   - Made `email` input field optional (removed `!` constraint)

### Core Code Changes

All core files were **reverted** to their pre-EmailAddress state:
- `src/Registry/TypeRegistry.php` - Removed scalar registration
- `src/Type/ObjectType/User.php` - Restored `email` field
- `src/Type/InterfaceType/Commenter.php` - Restored `email` field
- `src/Type/ObjectType/CommentAuthor.php` - Restored `email` field
- `src/Type/ObjectType/Comment.php` - Restored `email` edge field
- `src/Mutation/UserCreate.php` - Restored `email` input
- `src/Mutation/UserRegister.php` - Restored `email` input
- `src/Data/UserMutation.php` - Restored `email` input
- `src/Model/User.php` - Restored `email` property
- `src/Model/CommentAuthor.php` - Restored `email` property
- `src/WPGraphQL.php` - Removed `register_email_address_settings_fields()`
- `src/Deprecated.php` - Removed email deprecation methods

### Documentation

- Moved `docs/scalar-email-address.md` content to experiment READMEs
- Updated `docs/custom-scalars.md` to reflect EmailAddress is experimental
- Created comprehensive READMEs for both experiments
- Added experiments to `src/Experimental/README.md`

### Test Organization

**Experiment Tests (New Locations)**:
- `tests/wpunit/experiments/email-address-scalar/EmailAddressTest.php`
- `tests/wpunit/experiments/email-address-scalar-fields/UserEmailAddressFieldsTest.php`

**Core Tests (Reverted)**:
- Removed EmailAddress-specific tests from `UserObjectMutationsTest.php`
- Removed EmailAddress-specific tests from `UserObjectQueriesTest.php`
- Removed EmailAddress-specific tests from `SettingQueriesTest.php`

## Test Results

### Experiment Tests
```
✅ EmailAddressTest: 5 tests, 10 assertions
✅ UserEmailAddressFieldsTest: 7 tests, 24 assertions
```

### Core Tests (Regression Testing)
```
✅ UserObjectMutationsTest: 26 tests, 62 assertions
✅ UserObjectQueriesTest: 10 tests, 64 assertions
✅ SettingQueriesTest: 11 tests, 34 assertions
```

**Total**: 59 tests, 194 assertions - All passing ✅

## Backward Compatibility

The experiments maintain full backward compatibility:

1. **Without Experiments Enabled**:
   - All existing `email` fields work as before
   - No schema changes
   - No breaking changes

2. **With `email-address-scalar` Only**:
   - `EmailAddress` scalar available for use
   - No schema field changes
   - Useful for extension developers

3. **With Both Experiments Enabled**:
   - New `emailAddress` fields available
   - Old `email` fields deprecated but still functional
   - Both input fields work (with validation)
   - Deprecation warnings shown in debug mode

## GraphQL Schema Changes (When Both Enabled)

### New Fields
- `User.emailAddress: EmailAddress`
- `Commenter.emailAddress: EmailAddress`
- `CommentAuthor.emailAddress: EmailAddress`
- `GeneralSettings.adminEmail: EmailAddress`
- `CommentToCommenterConnectionEdge.emailAddress: EmailAddress`

### New Input Fields
- `CreateUserInput.emailAddress: EmailAddress`
- `UpdateUserInput.emailAddress: EmailAddress`
- `RegisterUserInput.emailAddress: EmailAddress`

### Deprecated (Still Functional)
- `User.email: String` → use `User.emailAddress`
- `Commenter.email: String` → use `Commenter.emailAddress`
- `CommentAuthor.email: String` → use `CommentAuthor.emailAddress`
- `GeneralSettings.email: String` → use `GeneralSettings.adminEmail`
- `CommentToCommenterConnectionEdge.email: String` → use `.emailAddress`
- `CreateUserInput.email: String` → use `.emailAddress`
- `UpdateUserInput.email: String` → use `.emailAddress`
- `RegisterUserInput.email: String` → use `.emailAddress`

## Files Created/Modified

### New Files
- `src/Experimental/Experiment/EmailAddressScalarExperiment/EmailAddress.php`
- `src/Experimental/Experiment/EmailAddressScalarExperiment/EmailAddressScalarExperiment.php`
- `src/Experimental/Experiment/EmailAddressScalarExperiment/README.md`
- `src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/EmailAddressScalarFieldsExperiment.php`
- `src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/README.md`
- `tests/wpunit/experiments/email-address-scalar/EmailAddressTest.php`
- `tests/wpunit/experiments/email-address-scalar-fields/UserEmailAddressFieldsTest.php`
- `EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR.md`
- `EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR_FIELDS.md`

### Modified Files
- `src/Experimental/ExperimentRegistry.php` - Registered both experiments
- `src/Experimental/README.md` - Added experiments to table
- `src/Experimental/Experiment/AbstractExperiment.php` - Improved `is_active()` caching for tests
- `docs/custom-scalars.md` - Removed EmailAddress as core feature

### Deleted Files
- `docs/scalar-email-address.md` - Content moved to experiment READMEs

## Key Learnings

1. **GraphQL Interface Inheritance**
   - Fields registered on interfaces automatically appear on implementing types
   - No need to re-register on each type
   - Cleaner and prevents duplicate registration errors

2. **WPGraphQL Mutation Filters**
   - `graphql_mutation_input` filter (in `WPMutationType`) is perfect for input normalization
   - Runs before `mutateAndGetPayload` callbacks
   - Cleaner than wrapping resolvers

3. **Field Deprecation**
   - Use `add_filter` to modify existing field definitions
   - Wrap resolvers to add deprecation warnings
   - Don't re-register existing fields (causes duplicates)

4. **Test Environment Setup**
   - Use `update_option('graphql_experiments_settings', ...)` for reliable test setup
   - Call `ExperimentRegistry::reset()` after option changes
   - Grant `super_admin` capabilities in multisite test environments

## Next Steps

1. ✅ Complete refactoring
2. ✅ All tests passing
3. ✅ Documentation complete
4. 📝 Create formal experiment proposals (draft complete)
5. 🔄 User feedback and iteration
6. 🎯 Potential graduation to core (if feedback is positive)

## How to Enable

### Via Filter (for testing/development)
```php
add_filter( 'graphql_experimental_features_override', function( $experiments ) {
    $experiments['email-address-scalar'] = true;
    $experiments['email-address-scalar-fields'] = true;
    return $experiments;
}, 10, 1 );
```

### Via Database Option
```php
$settings = get_option( 'graphql_experiments_settings', [] );
$settings['email-address-scalar_enabled'] = 'on';
$settings['email-address-scalar-fields_enabled'] = 'on';
update_option( 'graphql_experiments_settings', $settings );
```

### Via WP-Admin
Navigate to **GraphQL → Settings → Experiments** and enable the desired experiments.

---

**Status**: ✅ Complete and ready for review
**Date**: October 29, 2025
**Branch**: `scalar/email`

