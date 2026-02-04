# Contributing

## Local Development

```bash
# Setup
npm install
npm run wp-env start

# Development
npm start

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
