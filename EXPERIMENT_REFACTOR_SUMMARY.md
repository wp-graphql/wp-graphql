# EmailAddress Scalar Experiments - Refactor Summary

## ✅ Completed

### 1. Created Two Experiments

#### **Experiment 1: email-address-scalar**
- **Location**: `src/Experimental/Experiment/EmailAddressScalarExperiment/`
- **Files Created**:
  - `EmailAddressScalarExperiment.php` - Main experiment class
  - `EmailAddress.php` - Scalar type implementation (moved from core)
  - `README.md` - Comprehensive documentation
- **Purpose**: Registers the EmailAddress scalar type for validation
- **Dependencies**: None
- **Status**: ✅ Complete

#### **Experiment 2: email-address-scalar-fields**
- **Location**: `src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/`
- **Files Created**:
  - `EmailAddressScalarFieldsExperiment.php` - Main experiment class
  - `README.md` - Comprehensive documentation
- **Purpose**: Adds emailAddress fields to core types (User, Commenter, CommentAuthor, GeneralSettings)
- **Dependencies**: Requires `email-address-scalar` to be active
- **Status**: ✅ Complete

### 2. Registered Experiments

- ✅ Updated `src/Experimental/ExperimentRegistry.php` with both experiments
- ✅ Added imports for both experiment classes
- ✅ Updated `src/Experimental/README.md` with experiment table entries

### 3. Reverted Core Files

All EmailAddress-related changes have been removed from core:

- ✅ `src/Registry/TypeRegistry.php` - Removed EmailAddress::register_scalar() call and import
- ✅ `src/Type/ObjectType/User.php` - Reverted emailAddress field back to email (String)
- ✅ `src/Type/InterfaceType/Commenter.php` - Reverted emailAddress field back to email (String)
- ✅ `src/Type/ObjectType/CommentAuthor.php` - Reverted emailAddress field back to email (String)
- ✅ `src/Type/ObjectType/Comment.php` - Reverted edge field back to email (String)
- ✅ `src/Mutation/UserCreate.php` - Reverted emailAddress input back to email (String)
- ✅ `src/Mutation/UserRegister.php` - Reverted emailAddress input back to email (String)
- ✅ `src/Data/UserMutation.php` - Removed emailAddress input field and resolve_email_input() method
- ✅ `src/Model/User.php` - Removed emailAddress property
- ✅ `src/Model/CommentAuthor.php` - Removed emailAddress property
- ✅ `src/WPGraphQL.php` - Removed register_email_address_settings_fields() method
- ✅ `src/Deprecated.php` - Removed all email deprecation methods

### 4. Cleaned Up Files

- ✅ Deleted `src/Type/Scalar/EmailAddress.php` (moved to experiment)
- ✅ Deleted `docs/scalar-email-address.md` (superseded by experiment READMEs)
- ✅ Updated `docs/custom-scalars.md` to remove EmailAddress references

## 🔄 Next Steps (TODO)

### 1. Test the Experiments

**Before moving test files**, verify the experiments work correctly:

#### Enable experiments and test:
```php
// In wp-config.php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'email-address-scalar'        => true,
    'email-address-scalar-fields' => true,
] );
```

#### Test scenarios:
1. **Both experiments OFF** (default):
   - ✅ User.email should return String
   - ✅ No EmailAddress scalar in schema
   - ✅ Mutations accept email (String)

2. **Only email-address-scalar ON**:
   - ✅ EmailAddress scalar available in schema
   - ✅ Can register custom fields with EmailAddress type
   - ✅ Core fields still use String (User.email)

3. **Both experiments ON**:
   - ✅ EmailAddress scalar available
   - ✅ User.emailAddress field available (EmailAddress type)
   - ✅ User.email field available (deprecated String)
   - ✅ Commenter.emailAddress available
   - ✅ CommentAuthor.emailAddress available
   - ✅ GeneralSettings.adminEmail available
   - ✅ Mutation inputs accept both email and emailAddress
   - ✅ Conflict detection when both provided

#### Run existing tests:
```bash
vendor/bin/codecept run wpunit Type/Scalar/EmailAddressTest
```

### 2. Move Test Files

**Only after tests pass**, reorganize test files:

```bash
# Create experiment test directories
mkdir -p tests/wpunit/Experiments/EmailAddressScalarExperiment
mkdir -p tests/wpunit/Experiments/EmailAddressScalarFieldsExperiment

# Move scalar tests
mv tests/wpunit/Type/Scalar/EmailAddressTest.php \
   tests/wpunit/Experiments/EmailAddressScalarExperiment/EmailAddressScalarTest.php

# Create field tests
# (New file to test fields experiment)
```

### 3. Create Experiment Proposal

Create a GitHub issue following `.github/ISSUE_TEMPLATE/experiment_proposal.yml`:

**Title**: Email Address Scalar Experiments

**Key Points**:
- **Problem**: Email fields use generic String types without validation
- **Solution**: Two experiments (scalar + fields)
- **Why Experiment**: Need community validation of API design and deprecation strategy
- **Success Criteria**: 5+ production users, no major bugs, community consensus
- **Migration Plan**: Dual-field approach with deprecation warnings

## 📋 How It Works

### Experiment Architecture

```
email-address-scalar (no dependencies)
  └── Registers EmailAddress scalar type
      └── Used by: email-address-scalar-fields

email-address-scalar-fields (requires: email-address-scalar)
  └── Adds emailAddress fields to:
      - User
      - Commenter
      - CommentAuthor
      - GeneralSettings
  └── Adds deprecated email fields for backward compatibility
  └── Provides dual input support in mutations
```

### User Benefits

1. **Extension Authors**: Can opt-in to scalar only
2. **API Consumers**: Can opt-in to fields for validation
3. **Backward Compatibility**: Deprecated fields still work
4. **Gradual Migration**: Clear migration path

## 🚀 Activation

### Via WordPress Admin
1. Navigate to **GraphQL > Settings > Experiments**
2. Enable "Email Address Scalar"
3. (Optional) Enable "Email Address Scalar Fields"
4. Click **Save Changes**

### Via wp-config.php
```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'email-address-scalar'        => true,
    'email-address-scalar-fields' => true,
] );
```

### Via Filter
```php
add_filter( 'wp_graphql_experiment_email_address_scalar_enabled', '__return_true' );
add_filter( 'wp_graphql_experiment_email_address_scalar_fields_enabled', '__return_true' );
```

## 📝 Schema Changes

### With Experiments OFF (Default)
```graphql
type User {
  email: String  # Unchanged
}

type Commenter {
  email: String  # Unchanged
}
```

### With Both Experiments ON
```graphql
scalar EmailAddress

type User {
  email: String  # Deprecated, but still works
  emailAddress: EmailAddress  # New, validated
}

type Commenter {
  email: String  # Deprecated, but still works
  emailAddress: EmailAddress  # New, validated
}

type GeneralSettings {
  email: String  # Deprecated, but still works
  adminEmail: EmailAddress  # New, validated
}

input CreateUserInput {
  email: String  # Deprecated, but still works
  emailAddress: EmailAddress  # New, validated
}
```

## 🔍 Verification Checklist

Before considering this complete:

- [ ] Both experiments load without errors
- [ ] EmailAddress scalar validates correctly
- [ ] Fields only appear when experiments are enabled
- [ ] Deprecated fields show warnings in debug mode
- [ ] Mutations accept both old and new inputs
- [ ] Conflict detection works (error when both provided)
- [ ] All existing tests pass
- [ ] Documentation is accurate

## 📚 Documentation

### Experiment READMEs
- [Email Address Scalar](src/Experimental/Experiment/EmailAddressScalarExperiment/README.md)
- [Email Address Scalar Fields](src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/README.md)

### Core Docs
- [Experiments API](src/Experimental/README.md)
- [Using Experiments](docs/experiments-using.md)
- [Creating Experiments](docs/experiments-creating.md)

## 🎯 Success Metrics

For these experiments to graduate to core:

1. **Community Validation**: 5+ production implementations
2. **Stability**: No major bugs over 2+ releases
3. **Performance**: No significant performance concerns
4. **Migration**: Users successfully migrate from deprecated fields
5. **Consensus**: Agreement on dual-field deprecation approach

## 💡 Notes for Reviewers

- This is a **non-breaking change** - experiments are opt-in
- Default behavior unchanged when experiments are off
- Provides clear migration path for future v3.0
- Follows established Experiments API patterns
- Comprehensive documentation and examples included

