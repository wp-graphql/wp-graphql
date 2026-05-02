import { defaultHooks } from '@wordpress/hooks';

/**
 * Hooks system used to extend the WPGraphQL IDE.
 *
 * Re-exports `@wordpress/hooks`'s `defaultHooks` so every internal module —
 * regardless of which webpack entry bundle it ends up in — shares a single
 * action/filter bus. (Previously this called `createHooks()` per import,
 * which gave each bundled entry its own instance and silently broke any
 * action fired in one entry that listeners in another entry expected.)
 *
 * `defaultHooks` is also what `@wordpress/hooks`'s named exports
 * (`doAction`, `addAction`, etc.) and the global `wp.hooks` delegate to,
 * so external scripts that use `wp.hooks` see the same bus.
 */
export default defaultHooks;
