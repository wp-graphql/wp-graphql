# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### Setup and Development
```bash
npm install                  # Install dependencies
npm run wp-env start        # Start WordPress environment (requires Docker)
npm start                   # Start development server with hot reload
npm run clean               # Remove all node_modules and build directories
npm run check-engines       # Verify Node/npm version compatibility
```

### Building
```bash
npm run build              # Production build (creates build/ directory and zip file)
npm run build:main         # Build using wp-scripts
npm run build:zip          # Create plugin zip file for distribution
```

### Testing
```bash
npm run test:unit          # Run Jest unit tests
npm run test:e2e           # Run Playwright E2E tests (requires wp-env running)
npm run test:e2e:ui        # Run E2E tests with UI
```

### Code Quality
```bash
npm run lint:js            # Check JavaScript/React code
npm run lint:js:fix        # Auto-fix JavaScript issues
npm run format             # Format code
composer check-cs          # Check PHP coding standards (uses phpcs.xml.dist)
composer fix-cs            # Fix PHP coding standards
composer phpstan           # Run PHP static analysis (level 8 - strict)
```

### Version Management
```bash
npm run changeset          # Create a changeset for version updates
npm run version            # Update versions based on changesets
npm run release            # Publish release
```

## Architecture Overview

### Plugin System
WPGraphQL IDE is a WordPress plugin that provides a modern GraphQL query editor. It consists of:

1. **Main Application** (`/src/`)
   - React-based IDE built on GraphiQL 3.0
   - Redux stores for state management (@wordpress/data)
   - Extensible via registry system for panels and toolbar buttons

2. **Plugin Architecture** (`/plugins/`)
   - Modular plugins that extend the IDE
   - Each plugin has its own build entry point
   - Current plugins: help-panel, query-composer-panel, ai-assistant-panel

3. **WordPress Integration**
   - Deep integration with WordPress admin
   - Two access modes: drawer (slide-up from any admin page) and dedicated page
   - Admin bar integration for quick access
   - Requires WPGraphQL plugin to be installed and active
   - Custom capability: `manage_graphql_ide` (assigned to administrators)

### Key Architectural Patterns

1. **Registry System** (`/src/registry/`)
   - Allows extensions to register panels and toolbar buttons
   - Uses WordPress-style hooks and filters
   - See ACCESS_FUNCTIONS.md for public API functions

2. **Redux Stores** (`/src/stores/`)
   - `app` - Main application state
   - `activity-bar` - Manages sidebar panels
   - `document-editor` - Editor state and documents
   - `primary-sidebar` - Sidebar UI state
   - All stores are part of the public API and must maintain backward compatibility

3. **Build System**
   - Multiple entry points for main app and plugins
   - WordPress Scripts for build configuration
   - Webpack with externalized React, ReactDOM, and GraphQL
   - Build outputs: Main app → `/build/`, Plugins → `/plugins/[name]/build/`

### Development Environment

- **WordPress Environment** (.wp-env.json)
  - Port: 8888
  - Includes WPGraphQL plugin from WordPress.org
  - WP_DEBUG enabled
  - Docker required

- **Requirements**
  - PHP 7.4+ (composer.json requires 8.0+)
  - WordPress 5.0+ (tested up to 6.8)
  - Node.js and npm (check versions with `npm run check-engines`)

### Development Notes

- The IDE can be accessed at `/wp-admin/admin.php?page=graphql-ide` or via the admin bar
- Hot reload works in development mode (`npm start`)
- E2E tests require wp-env to be running
- The project follows WordPress coding standards for PHP and uses @wordpress/scripts for JavaScript
- When modifying Redux stores, ensure backward compatibility as they are part of the public API
- Breaking changes policy: No breaking changes to access functions or public Redux stores (see README.md)
- Custom hooks and filters are documented in ACTIONS_AND_FILTERS.md
- Text domain for i18n: `wpgraphql-ide`