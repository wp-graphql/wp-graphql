# Contributing

## Local Development

This plugin is part of the WPGraphQL monorepo. See the main [CONTRIBUTING.md](../../docs/CONTRIBUTING.md) for full details.

### Quick Start

```bash
# From monorepo root
npm install
npm run wp-env start

# Development (from plugin directory or monorepo root)
npm start -w @wpgraphql/wp-graphql-ide

# Access
# http://localhost:8888/wp-admin (admin/password)
```

## Commands

```bash
npm start              # Development build (watch mode)
npm run build         # Production build
npm run build:zip     # Create plugin zip
npm run wp-env stop   # Stop environment
npm run test:e2e      # Run tests
npm run lint:js:fix   # Fix linting
```

## Requirements

- Node.js + npm
- Docker (for wp-env)
