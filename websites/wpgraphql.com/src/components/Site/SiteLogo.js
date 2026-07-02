import { WPGraphQLLogo, WPGraphQLLogoMark } from "./WPGraphQLLogo"

/**
 * Renders the WPGraphQL identity. By default shows the wordmark + sub-label
 * lockup; pass `markOnly` to render just the elephant mark.
 */
export default function SiteLogo({
  width,
  height,
  size,
  markOnly,
  variant = "default",
  subLabel,
  className,
}) {
  const resolvedSize = size ?? width ?? height ?? 40

  if (markOnly) {
    return (
      <WPGraphQLLogoMark
        size={resolvedSize}
        variant={variant}
        className={className}
      />
    )
  }

  return (
    <WPGraphQLLogo
      size={resolvedSize}
      variant={variant}
      subLabel={subLabel}
      className={className}
    />
  )
}
