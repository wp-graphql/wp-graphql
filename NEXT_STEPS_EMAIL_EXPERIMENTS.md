# Next Steps - EmailAddress Scalar Experiments

## ✅ Refactoring Complete!

The EmailAddress scalar feature has been successfully refactored into two opt-in experiments. All tests are passing and the code is ready for review.

---

## What's Ready

1. ✅ **Two Experiments**:
   - `email-address-scalar` - The scalar type
   - `email-address-scalar-fields` - Fields using the scalar

2. ✅ **Complete Test Coverage**:
   - 12 experiment tests (34 assertions)
   - 47 core regression tests (160 assertions)
   - All passing

3. ✅ **Documentation**:
   - Experiment READMEs in each experiment directory
   - Draft experiment proposals ready
   - Updated main Experiments README

4. ✅ **Core Code Reverted**:
   - No breaking changes to core
   - Full backward compatibility maintained

---

## Recommended Next Steps

### 1. Review the Code
- [ ] Review experiment implementation files
- [ ] Review test coverage
- [ ] Review documentation

### 2. Test Manually
Enable the experiments and test in GraphiQL:

```php
// Add to wp-config.php or a mu-plugin for testing
add_filter( 'graphql_experimental_features_override', function( $experiments ) {
    $experiments['email-address-scalar'] = true;
    $experiments['email-address-scalar-fields'] = true;
    return $experiments;
}, 10, 1 );
```

Then try queries like:
```graphql
query {
  user(id: "dXNlcjox") {
    email          # deprecated String
    emailAddress   # new EmailAddress scalar
  }
  generalSettings {
    email          # deprecated String
    adminEmail     # new EmailAddress scalar
  }
}

mutation {
  createUser(input: {
    username: "testuser"
    emailAddress: "test@example.com"  # new field
    password: "password123"
  }) {
    user {
      id
      emailAddress
    }
  }
}
```

### 3. Finalize Experiment Proposals
The draft proposals are in:
- `EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR.md`
- `EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR_FIELDS.md`

You may want to:
- [ ] Copy to GitHub issues using the experiment proposal template
- [ ] Get community feedback
- [ ] Refine based on feedback

### 4. Update the Pull Request
Original PR: https://github.com/wp-graphql/wp-graphql/pull/3423

Options:
- **Option A**: Update the existing PR with the new experiment-based approach
- **Option B**: Create new PR(s) for the experiments
- **Option C**: Close the original PR and reference it in new experiment proposal issues

### 5. Consider Documentation Updates
- [ ] Add examples to experiment READMEs showing usage
- [ ] Add migration guide for when/if experiments graduate
- [ ] Update CHANGELOG with experiment additions

---

## Files to Review

### Core Experiment Files
```
src/Experimental/Experiment/
├── EmailAddressScalarExperiment/
│   ├── EmailAddress.php
│   ├── EmailAddressScalarExperiment.php
│   └── README.md
└── EmailAddressScalarFieldsExperiment/
    ├── EmailAddressScalarFieldsExperiment.php
    └── README.md
```

### Test Files
```
tests/wpunit/experiments/
├── email-address-scalar/
│   └── EmailAddressTest.php
└── email-address-scalar-fields/
    └── UserEmailAddressFieldsTest.php
```

### Documentation
```
- src/Experimental/README.md (updated)
- docs/custom-scalars.md (updated)
- EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR.md (new)
- EXPERIMENT_PROPOSAL_EMAIL_ADDRESS_SCALAR_FIELDS.md (new)
- REFACTORING_SUMMARY.md (new)
- EXPERIMENT_TEST_STATUS.md (updated)
```

---

## Questions to Consider

1. **Naming**: Are the experiment names clear and descriptive?
   - `email-address-scalar`
   - `email-address-scalar-fields`

2. **Dependencies**: Is the dependency structure appropriate?
   - `email-address-scalar-fields` depends on `email-address-scalar`
   - Extension authors can enable just the scalar without the fields

3. **Graduation Path**: What criteria should be met for graduation to core?
   - Time in the wild (e.g., 6 months?)
   - User feedback
   - Performance metrics
   - Breaking change considerations

4. **Versioning**: When should these experiments be marked for removal if not graduated?
   - Include in experiment config?

---

## Test Commands Reference

```bash
# Run all experiment tests
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/experiments composer run-test

# Run scalar tests
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/experiments/email-address-scalar composer run-test

# Run fields tests
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/experiments/email-address-scalar-fields composer run-test

# Run core user tests (regression check)
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/UserObjectMutationsTest.php composer run-test
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/UserObjectQueriesTest.php composer run-test
WP_VERSION=6.3 PHP_VERSION=8.2 DEBUG=0 SUITES=tests/wpunit/SettingQueriesTest.php composer run-test
```

---

## Key Achievements 🎉

- ✅ **Zero breaking changes** - All core code reverted
- ✅ **Full backward compatibility** - Old fields still work
- ✅ **Comprehensive testing** - 59 tests, 194 assertions
- ✅ **Clean architecture** - Interface inheritance, proper filters
- ✅ **Complete documentation** - READMEs, proposals, examples
- ✅ **Ready for feedback** - Opt-in experiments allow safe iteration

---

**Status**: Ready for review and feedback  
**Branch**: `scalar/email`  
**Date**: October 29, 2025

