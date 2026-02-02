# WPGraphQL Product Requirements Document

## Monorepo Structure

This repository is a monorepo containing the WPGraphQL ecosystem of plugins and related projects. The repository is organized as follows:

```
wp-graphql/
├── plugins/
│   ├── wp-graphql/              # Core WPGraphQL plugin
│   ├── wp-graphql-smart-cache/  # Smart Cache extension plugin
│   └── [future plugins]         # WPGraphQL IDE, WPGraphQL for ACF, etc.
├── docs/                        # Shared contributor documentation
├── bin/                         # Shared scripts
├── .wp-env.json                 # Shared WordPress environment config
├── package.json                 # Root workspace configuration
└── turbo.json                   # Turborepo build orchestration
```

Each plugin in the `plugins/` directory is a self-contained WordPress plugin with its own `composer.json`, `package.json`, and plugin structure. The monorepo uses npm workspaces and Turborepo for managing dependencies and builds across all plugins.

### Benefits of Monorepo Structure

- **Unified Testing**: Test plugins together to catch integration issues
- **Shared Infrastructure**: One CI/CD setup, one test environment
- **Atomic Changes**: Changes across plugins can be a single PR
- **Simplified Dependencies**: Easier to keep plugins compatible
- **Consistent Tooling**: Same linting, testing, and build tools across all plugins

## Product Overview

WPGraphQL is a free, open-source WordPress plugin that provides an extendable GraphQL API for WordPress websites. This monorepo contains the core WPGraphQL plugin as well as official extension plugins that enhance the WPGraphQL ecosystem.

The core WPGraphQL plugin provides a modern, performant GraphQL API that directly interfaces with WordPress internal data structures and registries, allowing developers to build headless WordPress applications.

### WPGraphQL Ecosystem

- **WPGraphQL** (core): The main GraphQL API plugin for WordPress
- **WPGraphQL Smart Cache**: Provides caching and cache invalidation for WPGraphQL queries
- **WPGraphQL IDE** (planned): Enhanced debugging and development tools
- **WPGraphQL for ACF** (planned): Advanced Custom Fields integration
- **wpgraphql.com website** (planned): Official website and documentation

### Core Value Proposition
- Enable headless WordPress architecture with a modern GraphQL API
- Improve performance through selective data fetching
- Provide a modern developer experience for WordPress
- Enable better integrations with modern frontend frameworks
- Maintain WordPress security model and permissions

## Target Users

### Primary Users

1. WordPress Developers
   - Building headless WordPress applications
   - Integrating WordPress with modern frontend frameworks
   - Creating custom plugins that extend WPGraphQL

2. Frontend Developers
   - Consuming WordPress data in React, Vue, or other frontend applications
   - Building JAMstack websites with WordPress as a backend
   - Developing mobile applications that use WordPress data

3. Agency Developers
   - Building enterprise WordPress solutions
   - Creating scalable multi-site implementations
   - Developing custom client solutions

### Secondary Users
1. WordPress Site Administrators
   - Managing GraphQL API access
   - Configuring API permissions
   - Monitoring API usage

2. Plugin Developers
   - Extending WPGraphQL with custom types and fields
   - Adding GraphQL support to existing WordPress plugins
   - Creating WPGraphQL extension plugins

## Core Features

### GraphQL API Endpoint
- Single endpoint at /graphql 
  - endpoint can be changed via code or WPGraphQL settings
- Handles GraphQL Queries and Mutations
- Supports HTTP POST and GET requests
- Implements GraphQL specification
- Provides schema introspection

### Schema Generation
- Automatically generates GraphQL schema for WordPress data
- Support for Post Types (built-in and custom)
- Support for Taxonomies (built-in and custom)
- Support for Users
- Support for Comments
- Support for WordPress settings

### Security & Authentication
- Can integrate with WordPress authentication
- Respects WordPress capabilities
- Supports JWT authentication via extension plugins (e.g., WPGraphQL JWT Authentication)
- Application Passwords support (WordPress 5.6+, may be enhanced in future releases)
- Provides options for field-level access control
- Maintains WordPress access-control model

### Performance Features
- Selective data fetching
- Connection/pagination support
- Query batching
- Caching integration
- N+1 query prevention

### Developer Tools
- GraphiQL IDE integration
- Debug mode
- Query logging
- Performance metrics
- Schema exploration tools

### Extension System
- Hook and filter system for schema and data modification
- Custom Type registration API
- Field registration API
- Custom resolver support

## Technical Requirements

### WordPress Compatibility
- WordPress 6.0+ (required)
- PHP 7.4+ (required), PHP 8.x recommended
- MySQL/MariaDB (as required by WordPress, latest versions recommended)
- Standard WordPress plugin installation. Can be installed via composer as well.

### Security Requirements
- Input sanitization
- Output escaping
- Follow WordPress Access Control standards

### API Standards
- GraphQL Specification compliance
- Relay specification support
- REST API coexistence
- Proper error handling
- Clear error messages

## Integration Requirements

### Frontend Framework Support
- React compatibility
- Vue.js compatibility
- Next.js compatibility
- Svelte compatibility
- Astro compatibility
- Gatsby compatibility
- Apollo Client support

### Plugin Ecosystem
- WooCommerce integration
- ACF integration
- Yoast SEO integration
- Gravity Forms integration
- Custom plugin extensibility
- Multi-plugin compatibility

### Development Tools
- WP-CLI support (limited)
- Composer integration
- npm package management
- wp-env (docker) environment
- CI/CD pipeline support

## Success Metrics

### Performance Metrics (ideals)
- Query response times
- Server resource usage
- Cache hit rates
- Error rates
- Concurrent user handling

### Developer Metrics
- GitHub stars and forks
- Active installations
- Community contributions
- Documentation usage
- Support forum activity

### User Success Metrics
- Successful installations
- API uptime
- Query success rates
- Extension adoption
- User satisfaction
- Query response times

## Future Considerations

### Planned Features
- Real-time subscriptions
- Improved debugging tools (WPGraphQL IDE - planned)
- Better error reporting
- Performance optimizations
- Custom Scalars
- Support for additional directives

**Note:** WPGraphQL Smart Cache is already available in `plugins/wp-graphql-smart-cache/` and is listed in the WPGraphQL Ecosystem section above.

### Scalability Goals
- Increased Support for high-traffic sites
- Enterprise-level performance
- Multi-site network support (currently works with per-site endpoints; network-level endpoint may be considered in the future)

### Community Growth
- Documentation expansion
- Tutorial development
- Example project creation
- Community event participation
- Contributor program development

## Implementation Guidelines

### Code Standards
- WordPress Coding Standards
- PHPStan Level 10 compliance
- Code coverage maintained and improved (enforced via Codecov with auto-target and 1% threshold)
- Integration test suite
- E2E test coverage

### Documentation Requirements
- Inline code documentation
- API documentation
- Usage examples
- Integration guides
- Troubleshooting guides

### Release Process
- Semantic versioning
- Change log maintenance
- Beta testing process
- Release candidates
- Backward compatibility (breaking changes communicated with semver)

### Support Requirements
- GitHub issue tracking
- WordPress.org support
- Documentation updates
- Security advisories
- Version compatibility
