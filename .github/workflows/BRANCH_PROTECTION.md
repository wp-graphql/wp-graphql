# GitHub Branch Protection Configuration

This document explains how to configure GitHub branch protection rules when workflows have conditional jobs.

## The Problem

When workflows have conditional jobs (using `if:` statements), those jobs might be skipped. GitHub branch protection requires specific status checks to pass, but:

- Skipped jobs don't create a status check
- Jobs with `continue-on-error: true` can fail but still pass
- Conditional jobs may not run on every PR

## Solution: Final Status Check Job

Add a final job that **always runs** and depends on all conditional jobs. This creates a consistent status check name for branch protection.

### Example Pattern

```yaml
jobs:
  # Conditional jobs (may be skipped)
  job-a:
    if: condition == 'true'
    runs-on: ubuntu-latest
    steps:
      - run: echo "Job A"
  
  job-b:
    if: other_condition == 'true'
    runs-on: ubuntu-latest
    steps:
      - run: echo "Job B"
  
  # Final status check (ALWAYS RUNS)
  status-check:
    name: "Integration Tests"
    runs-on: ubuntu-latest
    needs: [job-a, job-b]
    if: always()  # Run even if previous jobs were skipped or failed
    steps:
      - name: Check job results
        run: |
          # Check if any required jobs failed (excluding skipped)
          if [ "${{ needs.job-a.result }}" == "failure" ] || [ "${{ needs.job-b.result }}" == "failure" ]; then
            echo "❌ One or more required jobs failed"
            exit 1
          fi
          # Allow skipped jobs (they didn't need to run)
          if [ "${{ needs.job-a.result }}" == "skipped" ] || [ "${{ needs.job-b.result }}" == "skipped" ]; then
            echo "ℹ️ Some jobs were skipped (expected for conditional jobs)"
          fi
          echo "✅ All required jobs passed"
```

### Key Points

1. **`if: always()`** - Ensures the final job runs even if previous jobs were skipped or failed
2. **`needs: [job-a, job-b]`** - Waits for all conditional jobs to complete (even if skipped)
3. **Check `result`** - Use `needs.<job-id>.result` to check if jobs failed (not skipped)
4. **Allow skipped** - Skipped jobs are expected for conditional workflows

## Branch Protection Configuration

In GitHub branch protection settings:

1. Go to **Settings → Branches → Branch protection rules**
2. Add a rule for your branch (e.g., `main`)
3. Under **"Require status checks to pass before merging"**:
   - ✅ Check **"Require branches to be up to date before merging"**
   - Add the **final status check job name** (e.g., `Integration Tests`)
   - ❌ **Do NOT** add individual conditional job names

### Example Status Check Names

Based on your workflows, use these final status check job names:

- `Integration Tests` (from `integration-tests.yml`)
- `Lint` (from `lint.yml`)
- `Schema Linter` (from `schema-linter.yml`)
- `Smoke Test` (from `smoke-test.yml`)
- `JS E2E Tests` (from `js-e2e-tests.yml`)

## Handling Experimental Jobs

For jobs with `continue-on-error: true` (experimental jobs):

```yaml
status-check:
  needs: [required-job, experimental-job]
  if: always()
  steps:
    - name: Check required jobs
      run: |
        if [ "${{ needs.required-job.result }}" == "failure" ]; then
          echo "❌ Required job failed"
          exit 1
        fi
        # Experimental jobs can fail (continue-on-error: true)
        if [ "${{ needs.experimental-job.result }}" == "failure" ]; then
          echo "⚠️ Experimental job failed (allowed)"
        fi
        echo "✅ All required jobs passed"
```

## Alternative: Workflow-Level Status Checks

Instead of individual job status checks, you can require the **entire workflow** to pass:

1. In branch protection, add: `Integration Tests / status-check` (workflow name + job name)
2. This requires the workflow file itself to exist and the final job to pass

## Implementation Checklist

For each workflow with conditional jobs:

- [ ] Add a final `status-check` job that always runs (`if: always()`)
- [ ] Make it depend on all conditional jobs (`needs: [...]`)
- [ ] Check job results and fail only if required jobs failed
- [ ] Allow skipped jobs (they're expected)
- [ ] Add the final job name to branch protection rules
- [ ] Test with a PR that triggers some but not all conditional jobs

## Current Workflow Status

### ✅ Already Have Final Status Checks
- `integration-tests.yml` - Has final status check job
- `lint.yml` - Has final status check job
- `smoke-test.yml` - Has final status check job
- `js-e2e-tests.yml` - Has final status check job
- `schema-linter.yml` - Has final status check job (uses matrix strategy, all jobs should run)

### 📝 Notes
- All workflows with conditional jobs now have final status check jobs
- Workflows always run on PRs (no path filters on triggers) to ensure status checks are created
- Tests/linting are conditionally executed based on change detection, but the workflow always runs
- `schema-linter.yml` uses a matrix strategy, so all jobs should run (no conditionals)
