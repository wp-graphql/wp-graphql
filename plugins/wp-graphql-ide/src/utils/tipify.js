import React from 'react';

/**
 * Splits a snackbar string at the first " Tip: " marker and returns JSX
 * that renders the quip and the tip as separate block lines. Designed
 * for the play-mash and schema-refresh easter-egg milestones — the
 * comical part needs visual breathing room from the actionable tip.
 *
 * Non-strings and strings without a tip marker pass through unchanged.
 *
 * @param {string|React.ReactNode} content Notice content.
 *
 * @return {React.ReactNode} The original content or a two-line fragment.
 */
export function tipify(content) {
	if (typeof content !== 'string') {
		return content;
	}
	const marker = ' Tip: ';
	const idx = content.indexOf(marker);
	if (idx === -1) {
		return content;
	}
	const quip = content.slice(0, idx + 1); // include the trailing period
	const tip = content.slice(idx + 1).trim(); // "Tip: ..."
	return (
		<>
			<span className="wpgraphql-ide-snackbar-tip-quip">{quip}</span>
			<span className="wpgraphql-ide-snackbar-tip">{tip}</span>
		</>
	);
}
