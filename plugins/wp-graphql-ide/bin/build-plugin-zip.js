#!/usr/bin/env node
/**
 * Package the plugin as a properly-structured WordPress plugin ZIP.
 *
 * Why this exists (instead of just using `wp-scripts plugin-zip`):
 * --------------------------------------------------------------------
 * `@wordpress/scripts`' `plugin-zip` reads `package.json`'s `name`
 * field to derive both the output filename AND adds the discovered
 * files flat at the archive root (no top-level directory wrapping).
 *
 * Two failure modes when the workspace name is scoped:
 *
 *   1. For `@wpgraphql/wp-graphql-ide` the output filename becomes
 *      `@wpgraphql/wp-graphql-ide.zip` — `@wpgraphql/` is created as
 *      a real subdirectory in the CWD, which then collides with the
 *      next clean build.
 *
 *   2. WordPress, wp.org's plugin installer, and WordPress Playground
 *      all expect a plugin ZIP to contain a SINGLE top-level directory
 *      whose name equals the plugin slug (e.g. `wp-graphql-ide/…`).
 *      `wp-scripts plugin-zip`'s flat archive trips Playground's
 *      `installPlugin` step — it can't reliably locate the plugin
 *      directory and `activatePlugin` then fails with
 *      "wasn't able to find the plugin /wordpress/wp-content/plugins/<slug>".
 *
 * We reuse the same file-discovery pipeline (npm-packlist, honoring
 * the `files` field in package.json the same way `wp-scripts plugin-zip`
 * does), then add everything under a `<slug>/` prefix and write to
 * `<slug>.zip` at the workspace root. End-of-the-day artifact is what
 * WP / Playground / wp.org expect.
 *
 * Both `adm-zip` and `npm-packlist` are resolved through `@wordpress/scripts`'
 * dependency tree, which is already a devDep of this workspace — no new
 * deps to declare.
 */
const AdmZip = require( 'adm-zip' );
const { sync: packlist } = require( 'npm-packlist' );
const path = require( 'path' );
const fs = require( 'fs' );

const SLUG = 'wp-graphql-ide';
const OUTPUT = path.join( process.cwd(), `${ SLUG }.zip` );

process.stdout.write( `Creating ${ SLUG }.zip with top-level \`${ SLUG }/\`…\n\n` );

// Remove the previous archive and the scoped-name directory
// `wp-scripts plugin-zip` would have left behind on prior runs, so
// repeated builds don't accumulate or pick up stale state.
if ( fs.existsSync( OUTPUT ) ) {
	fs.rmSync( OUTPUT );
}
fs.rmSync( path.join( process.cwd(), '@wpgraphql' ), {
	recursive: true,
	force: true,
} );

const files = packlist();
const zip = new AdmZip();

for ( const file of files ) {
	process.stdout.write( `  Adding \`${ SLUG }/${ file }\`.\n` );
	// adm-zip's second arg is the directory inside the ZIP. Prefix
	// each file's source dir with the slug so we get
	// `wp-graphql-ide/build/wpgraphql-ide.js` inside the archive
	// rather than the flat `build/wpgraphql-ide.js` that `wp-scripts
	// plugin-zip` produces.
	const zipDir = path.posix.join( SLUG, path.posix.dirname( file ) );
	zip.addLocalFile( file, zipDir === SLUG + '/.' ? SLUG : zipDir );
}

zip.writeZip( OUTPUT );
process.stdout.write( `\nDone. ${ SLUG }.zip is ready! 🎉\n` );
