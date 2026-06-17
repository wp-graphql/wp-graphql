// Platform-aware keyboard-shortcut labels.
//
// `@wordpress/keycodes`'s `displayShortcut` is the same helper Gutenberg's
// menus use, so menu items, tooltips, and the welcome buffer all render
// the same conventions a WordPress admin already sees elsewhere:
//
//   Mac:    ⌘Enter / ⌘S / ⌘⇧P / ⌘⇧M
//   Others: Ctrl+Enter / Ctrl+S / Ctrl+Shift+P / Ctrl+Shift+M
import { displayShortcut } from '@wordpress/keycodes';

export const RUN_QUERY_LABEL = displayShortcut.primary('Enter');
export const SAVE_LABEL = displayShortcut.primary('S');
export const PRETTIFY_LABEL = displayShortcut.primaryShift('P');
export const MERGE_LABEL = displayShortcut.primaryShift('M');
