/**
 * Shared building blocks for the sibling-brand extension landing pages
 * (/extensions/*). Accent color comes from the surrounding `.theme-*` scope via
 * the `--primary` token, so these are brand-agnostic.
 *
 * - Eyebrow: icon + uppercase mono label flanked by gradient rules.
 * - SectionHeading: eyebrow + a two-line title whose second line is fully
 *   accent-colored (WPGraphQL / RadiQL style), with an optional intro.
 * - VisualPanel: a small inset "IDE surface" panel for inline mini-mocks. Uses
 *   the theme-aware .ide-* classes from globals.css (dark/light adaptive).
 *
 * Pass `icon` a heroicon component (e.g. CommandLineIcon for the IDE,
 * TableCellsIcon for ACF) to give each product a fitting eyebrow glyph.
 */

export function Eyebrow({ children, icon: Icon, align = "center" }) {
  return (
    <div
      className={`flex items-center gap-3 ${
        align === "center" ? "justify-center" : "justify-start"
      }`}
    >
      {align === "center" && (
        <span
          className="hidden h-px w-10 bg-gradient-to-r from-transparent to-primary/40 sm:block"
          aria-hidden="true"
        />
      )}
      <span className="inline-flex items-center gap-2 font-mono text-xs font-medium uppercase tracking-widest text-primary">
        {Icon && <Icon className="size-3.5" aria-hidden="true" />}
        {children}
      </span>
      <span
        className={`${align === "center" ? "hidden sm:block " : ""}h-px ${
          align === "center"
            ? "w-10 bg-gradient-to-l from-transparent to-primary/40"
            : "w-16 bg-gradient-to-r from-primary/40 to-transparent"
        }`}
        aria-hidden="true"
      />
    </div>
  )
}

export function SectionHeading({
  eyebrow,
  icon,
  lead,
  accent,
  intro,
  align = "center",
}) {
  return (
    <div className={align === "center" ? "mx-auto max-w-3xl text-center" : ""}>
      <Eyebrow icon={icon} align={align}>
        {eyebrow}
      </Eyebrow>
      <h2 className="mt-4 text-display-sm font-extrabold tracking-tight text-foreground sm:text-display-md">
        {lead}
        <br />
        <span className="text-primary">{accent}</span>
      </h2>
      {intro && (
        <p
          className={`mt-4 text-base text-muted-foreground sm:text-lg ${
            align === "center" ? "" : "max-w-xl"
          }`}
        >
          {intro}
        </p>
      )}
    </div>
  )
}

export function VisualPanel({ children, className = "" }) {
  return (
    <div
      className={`ide-bg ide-border ide-text mt-6 rounded-lg border p-4 font-mono text-xs leading-relaxed ${className}`}
    >
      {children}
    </div>
  )
}
