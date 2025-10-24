# What are Experiments?

WPGraphQL Experiments enable the core team to ship, iterate, and gather feedback on potential features without long-term compatibility commitments. This allows the community to "experience the future of WPGraphQL today" while maintaining a stable production API.

## Overview

Experiments are **optional, experimental features** built into WPGraphQL core that you can enable or disable through the WordPress admin. They allow the WPGraphQL team to:

- **Ship features faster**: Get new capabilities into users' hands without waiting for perfect implementation
- **Gather real feedback**: Learn how features perform in production environments
- **Iterate rapidly**: Make breaking changes to experiments without violating semantic versioning
- **Reduce risk**: Test features with opt-in users before committing to long-term support

## Experiments vs. Extensions vs. Feature Flags

Understanding the difference helps you choose the right tool for your needs:

| Aspect               | Experiments                       | Extensions (Plugins) | Feature Flags                 |
| -------------------- | --------------------------------- | -------------------- | ----------------------------- |
| **Location**         | Built into WPGraphQL core         | Separate plugins     | External configuration        |
| **Activation**       | WordPress admin settings          | Plugin activation    | Code or environment variables |
| **Breaking Changes** | Allowed during experimental phase | Not recommended      | Not applicable                |
| **Lifespan**         | Temporary (2-3 releases)          | Indefinite           | Project-specific              |
| **Maintenance**      | WPGraphQL core team               | Extension authors    | Application developers        |
| **Purpose**          | Testing future core features      | Custom functionality | Deployment control            |

### When to use each:

- **Experiments**: You want to try out upcoming WPGraphQL core features and provide feedback
- **Extensions**: You need custom functionality not planned for WPGraphQL core
- **Feature Flags**: You're controlling feature rollout in your own application code

## Why Experiments Matter

### For Users

- **Early access**: Try new features before they're officially released
- **Influence development**: Your feedback shapes what becomes part of WPGraphQL
- **Safe testing**: Enable/disable features without affecting your production site
- **No waiting**: Access cutting-edge capabilities without waiting for major releases

### For the WPGraphQL Team

- **Real-world validation**: Test features with actual users and use cases
- **Faster iteration**: Make improvements based on feedback without breaking existing sites
- **Risk reduction**: Find issues before features become permanent
- **Community engagement**: Involve the community in the development process

## Experiment Lifecycle

Every experiment follows a predictable lifecycle:

```
Proposed → Active → Feedback → Decision
                                    ↓
                    Graduate | Iterate | Deprecate
                        ↓         ↓          ↓
                    Stable   Active    Removed
```

1. **Proposed**: Feature idea is discussed and approved for experimentation
2. **Active Experiment**: Feature is available to enable but may change
3. **Feedback Period**: Community tests and provides feedback (2-3 releases)
4. **Decision Point**:
   - **Graduate**: Becomes a stable core feature (no more breaking changes)
   - **Iterate**: Continue experimenting with improvements
   - **Deprecate**: Marked for removal, users get migration guidance
5. **Final State**: Either stable in core or removed

### Expected Timeline

- **Active phase**: 2-3 major releases minimum
- **Deprecation notice**: At least 1 major release before removal
- **Total lifespan**: Most experiments resolve within 4-6 releases

## Current Experiments

To see which experiments are currently available:

1. Navigate to **GraphQL > Settings > Experiments** in your WordPress admin
2. Review the [Experiments README](https://github.com/wp-graphql/wp-graphql/tree/develop/src/Experimental) on GitHub
3. Check release notes for the `Experimental` badge

## Getting Started

Ready to try experiments?

- **For Users**: See [Using Experiments](/docs/experiments-using) to learn how to enable and test experiments
- **For Developers**: See [Creating Experiments](/docs/experiments-creating) to build your own experiments
- **For Contributors**: See [Contributing Experiments](/docs/experiments-contributing) to add experiments to WPGraphQL core

### Experiment Documentation

Each experiment includes comprehensive documentation in its own README.md file:

- **What it does**: Clear explanation of the experiment's purpose and functionality
- **Schema changes**: New types, fields, or modifications introduced
- **Usage examples**: GraphQL queries demonstrating the experiment in action
- **Dependencies**: Required and optional dependencies (if any)
- **Known limitations**: Current constraints or edge cases

When you activate an experiment, a link to its documentation is automatically included in the admin notice, providing immediate access to detailed information about how to use it.

## Principles

The Experiments API follows these core principles:

1. **Opt-in by default**: Experiments never affect users who don't explicitly enable them
2. **Clear communication**: Every experiment clearly states its purpose and status
3. **Time-bounded**: Experiments must reach a decision; they cannot remain experimental indefinitely
4. **Reversible**: Users can always disable experiments and return to stable behavior
5. **Isolated**: Experiments should be independent and not break core functionality when disabled

## FAQ

### Are experiments safe to use?

Experiments are **safe to enable** but come with caveats:

- ✅ They won't break your site when enabled
- ✅ They can be disabled at any time
- ⚠️ They may have breaking changes in future releases
- ⚠️ They may be removed if not graduated

**Recommendation**: Test experiments in staging before enabling in production.

### Will my site break if an experiment is removed?

No. When an experiment is removed or deprecated, you'll receive:

- **Advance notice**: At least one major release warning
- **Migration guide**: Instructions for adapting to changes
- **Grace period**: Time to test and migrate
- **Fallback behavior**: Your site will continue working with the experiment disabled

### Can I keep using an experiment indefinitely?

No. Experiments are temporary by design. They will either:

- **Graduate to core** (becomes permanent, always enabled)
- **Be deprecated and removed** (you'll need to migrate away)

This prevents accumulation of experimental code and keeps WPGraphQL maintainable.

### How do I provide feedback on an experiment?

We love feedback! You can:

1. **GitHub Issues**: Share detailed thoughts and use cases
2. **GitHub Issues**: Report bugs or unexpected behavior
3. **Slack**: Quick questions in the WPGraphQL Slack community
4. **Twitter/X**: Tag @wpgraphql with your experience

The more specific your feedback, the more valuable it is!

### Can plugins create experiments?

The current Experiments API is designed for WPGraphQL core features only. Plugin authors should:

- Use traditional settings/options for their own features
- Consider feature flags in their own codebase
- Build extensions that can be optionally activated

## Support

Need help with experiments?

- **Documentation**: Check the [Using Experiments](/docs/experiments-using) guide
- **Community**: Ask in [GitHub Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Issues**: Report bugs in [GitHub Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Slack**: Join the [WPGraphQL Community Slack](https://wpgraphql.com/community)

## Next Steps

- [Using Experiments](/docs/experiments-using) - How to enable and use experiments
- [Creating Experiments](/docs/experiments-creating) - How to build experiments
- [Contributing Experiments](/docs/experiments-contributing) - How to contribute to WPGraphQL
