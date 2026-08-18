/**
 * Decode HTML character references and common named entities in plain text.
 * Use after stripping tags from WordPress HTML so excerpts and summaries read naturally.
 *
 * @param {string | null | undefined} input
 * @returns {string}
 */
function decodeHtmlEntities(input) {
	if (input == null) {
		return "";
	}

	let s = String(input);

	s = s.replace(/&#x([0-9a-f]{1,6});/gi, (full, hex) => {
		const code = parseInt(hex, 16);
		if (!Number.isFinite(code) || code < 0 || code > 0x10ffff) {
			return full;
		}
		return String.fromCodePoint(code);
	});

	s = s.replace(/&#(\d{1,7});/g, (full, dec) => {
		const code = parseInt(dec, 10);
		if (!Number.isFinite(code) || code < 0 || code > 0x10ffff) {
			return full;
		}
		return String.fromCodePoint(code);
	});

	s = s.replace(/&nbsp;/gi, "\u00a0");
	s = s.replace(/&ldquo;/gi, "\u201c");
	s = s.replace(/&rdquo;/gi, "\u201d");
	s = s.replace(/&lsquo;/gi, "\u2018");
	s = s.replace(/&rsquo;/gi, "\u2019");
	s = s.replace(/&hellip;/gi, "\u2026");
	s = s.replace(/&mdash;/gi, "\u2014");
	s = s.replace(/&ndash;/gi, "\u2013");
	s = s.replace(/&quot;/gi, '"');
	s = s.replace(/&apos;/gi, "'");
	s = s.replace(/&lt;/gi, "<");
	s = s.replace(/&gt;/gi, ">");
	s = s.replace(/&amp;/gi, "&");

	return s;
}

module.exports = decodeHtmlEntities;
module.exports.decodeHtmlEntities = decodeHtmlEntities;
