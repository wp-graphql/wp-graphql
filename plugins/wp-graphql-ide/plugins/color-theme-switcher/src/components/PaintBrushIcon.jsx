import { SVG, Path } from '@wordpress/components';

/**
 * Inline paintbrush glyph — matches the visual weight of the other
 * `@wordpress/icons` topbar icons (cog, update) so the topbar row
 * reads as one set rather than a mix of icon libraries.
 */
export const paintBrushIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
		<Path d="M20.71 4.04a1 1 0 0 0-1.42 0l-9 9 2.83 2.83 9-9a1 1 0 0 0 0-1.41l-1.41-1.42ZM7 14c-1.66 0-3 1.34-3 3 0 1.31-1.16 2-2 2 .92 1.22 2.49 2 4 2 2.21 0 4-1.79 4-4 0-1.66-1.34-3-3-3Z" />
	</SVG>
);
