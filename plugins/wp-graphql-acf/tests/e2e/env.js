/**
 * E2E environment: ACF / ACF Extended variant.
 * Used to skip tests when the current run is ACF Free or Extended Free (no Extended Pro).
 *
 * CI sets INSTALL_ACF_PRO and INSTALL_ACF_EXTENDED_PRO from the matrix.
 * When unset (e.g. local), we assume "run all" so existing local workflows keep working.
 */
const rawPro = process.env.INSTALL_ACF_PRO;
const rawExtendedPro = process.env.INSTALL_ACF_EXTENDED_PRO;

/** ACF Pro is active (when env is set; unset = assume true so we don't skip locally). */
export const acfPro =
	rawPro === undefined || rawPro === '' || rawPro === 'true';

/** ACF Extended Pro is active. Only relevant when ACF Pro is used; ACF Extended is not installed with ACF Free. */
export const acfExtendedPro = rawExtendedPro === 'true';

/** ACF Extended (Free or Pro) is active. We only install Extended when ACF Pro is used. */
export const acfExtended = acfPro;

/** Skip condition: run only when ACF Pro is active (mirrors AcfProFieldCest). */
export function skipWhenNotAcfPro() {
	return !acfPro;
}

/** Skip condition: run only when ACF Extended is present (Pro + Extended Free or Pro; mirrors AcfeFieldCest). */
export function skipWhenNotAcfExtended() {
	return !acfExtended;
}

/** Skip condition: run only when ACF Extended Pro is active (mirrors AcfeProFieldCest). */
export function skipWhenNotAcfExtendedPro() {
	return !acfExtendedPro;
}
