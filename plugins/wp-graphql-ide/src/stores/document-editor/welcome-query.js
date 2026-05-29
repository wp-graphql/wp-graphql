/**
 * Boilerplate seeded into a fresh draft tab. GraphQL line comments
 * (`#`) make the welcome text safe to leave in place — running it
 * produces no operations rather than a parse error.
 *
 * Only shortcuts non-obvious or overridden by this build are listed:
 * - `Mod-Enter` (run query) — registered explicitly in IDELayout
 *   via `Prec.highest(keymap.of(...))`.
 * - `Ctrl-Space` — autocomplete; comes from CodeMirror 6's
 *   `completionKeymap` inside `basicSetup`. Identical on every
 *   platform (Mac included).
 * - `Tab` / `Shift-Tab` — indent / outdent; CodeMirror 6 defaults
 *   Tab to focus-out for accessibility, so we override via
 *   `keymap.of([indentWithTab])` in GraphQLEditor.
 *
 * Universal bindings (undo/redo, find, comment) come from
 * `basicSetup` and are intentionally not advertised here.
 */
import {
	RUN_QUERY_LABEL,
	PRETTIFY_LABEL,
	MERGE_LABEL,
} from '../../utils/shortcut-labels';

// Pad the key column so the description column lines up regardless
// of which platform variants of the labels got rendered.
const col = (key) => key.padEnd(14, ' ');

export const WELCOME_QUERY = `# Welcome to the WPGraphQL IDE
#
# Lines starting with "#" are comments.
#
# Example:
#
#   {
#     posts {
#       nodes {
#         id
#         title
#       }
#     }
#   }
#
# Shortcuts:
#
#   ${col(RUN_QUERY_LABEL)} Run query
#   ${col(PRETTIFY_LABEL)} Prettify query
#   ${col(MERGE_LABEL)} Merge fragments into query
#   ${col('Ctrl+Space')} Autocomplete
#   ${col('Tab')} Indent (Shift+Tab to outdent)
`;
