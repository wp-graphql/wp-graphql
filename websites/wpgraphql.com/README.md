![alt text](./src/img/icons/128x128.png "WPGraphQL Logo")

# WPGraphQL.com

[WPGraphQL.com](https://wpgraphql.com) is a Next.js site that uses WordPress as the CMS.

This repository contains the Next.js code to source data from WordPress and create pages using WordPress data.

## Contributing

### Setup

1. Install dependencies from the monorepo root:
   ```bash
   npm install
   ```

2. Copy the example environment file:
   ```bash
   cp .env.local.example .env.local
   ```

3. Update `.env.local` with your environment variables (see below)

4. Run the development server:
   ```bash
   npm run dev -w @wpgraphql/wpgraphql-com
   ```

### Building

To build the website:
```bash
npm run build -w @wpgraphql/wpgraphql-com
```

### Testing the possibleTypes.json Fix

To test that the build works when `possibleTypes.json` is missing (simulating Vercel deployment):

```bash
npm run test:build-without-possibletypes -w @wpgraphql/wpgraphql-com
```

This script will:
1. Backup the existing `possibleTypes.json` file (if it exists)
2. Delete the file to simulate the Vercel scenario
3. Run the build without the prebuild hook
4. Restore the file after testing

If the build succeeds, the fix is working correctly.

### Required Environment Variables

Copy `.env.local.example` to `.env.local` and fill in the values:

- `NEXT_PUBLIC_SITE_URL` - The public URL of the site
- `WPGRAPHQL_URL` - The GraphQL endpoint URL
- `NEXT_PUBLIC_WORDPRESS_URL` - The WordPress URL (used for previews and data fetching)
- `FAUSTWP_SECRET_KEY` - Secret key for FaustWP previews
- `GITHUB_TOKEN` - GitHub token for API calls (optional)
