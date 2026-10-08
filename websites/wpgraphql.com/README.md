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

2. Create `websites/wpgraphql.com/.env.local` and fill in the environment
   variables listed under [Environment Variables](#environment-variables).

3. Run the development server:
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

### Environment Variables

Set these in `websites/wpgraphql.com/.env.local` for local development, and in
the hosting environment for deployed builds.

Required:

- `NEXT_PUBLIC_SITE_URL` - The public URL of this site. Used for the feeds and
  their `<link rel="alternate">` tags, the WordPress sitemap route, and the
  `X-RadiQL-Origin-Host` header the GraphQL client sends so server-side
  requests are attributed to this app.
- `WPGRAPHQL_URL` - The GraphQL endpoint to source content from, including the
  `/graphql` path. `NEXT_PUBLIC_WPGRAPHQL_URL` takes precedence if both are set.
- `NEXT_PUBLIC_WORDPRESS_URL` - The WordPress backend's **site** URL (for
  example `https://contentwpgraphql.wpcomstaging.com`), not the `/graphql`
  endpoint and not a caching proxy sitting in front of it. `next.config.js`
  reads it for one purpose: allowlisting that hostname for `next/image`. Note
  that a backend behind Jetpack's Site Accelerator serves media from
  `i0.wp.com` rather than its own hostname, which `next.config.js` allowlists
  separately.
- `WPGRAPHQL_REVALIDATE_SECRET` - Shared secret for the on-demand ISR endpoint
  at `/api/revalidate`. The endpoint rejects every request when this is unset.

Optional:

- `GITHUB_TOKEN` - Raises the GitHub API rate limit when docs are fetched from
  the monorepo at build time.
- `NEXT_PUBLIC_GA_ID` - Google Analytics measurement ID. Note that `_app.js`
  loads the analytics scripts unconditionally, so leaving this unset does not
  disable them, it reports against an `undefined` ID.
- `WPGRAPHQL_CLIENT_DEBUG` - Set to `1` to force GraphQL client debug logging
  on, or `0` to force it off. Defaults to on outside production.

## Branding & design system

The extension landing pages (`/extensions/*`) and the site's theming use the
WPGraphQL product-family "sibling brand" system — a shared navy foundation with a
per-product accent (violet for IDE, emerald for ACF, rose for Smart Cache),
applied via scoped `.theme-*` classes in `src/styles/globals.css`. Logo
components live in `src/components/<Product>/` and the shared section building
blocks in `src/components/extensions/`.

The source-of-truth brand guides, tokens, and the WordPress.org asset generators
live in the monorepo's [`design/brand/`](../../design/brand/README.md) directory.
If you're adding or restyling a sibling-brand page or asset, start there.
