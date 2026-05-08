import dynamic from "next/dynamic"

/**
 * Renders a Heroicon by name (string passed from a WordPress Nav Menu Item).
 * Returns null when the icon prop is missing or doesn't resolve to a real
 * Heroicon export — without this guard, an invalid name produces a
 * "ForwardRef(LoadableComponent)" React error.
 *
 * @see: https://github.com/tailwindlabs/heroicons/issues/278#issuecomment-851594776
 */
export default function DynamicHeroIcon(props) {
  if (!props?.icon) return null

  const Icon = dynamic(() =>
    import(`@heroicons/react/24/outline/esm`).then((icons) => {
      const candidate = icons[props.icon]
      if (typeof candidate === "function") return candidate
      // Render nothing if the icon name didn't resolve to a component
      return () => null
    })
  )

  return <Icon className="h-6 w-6" aria-hidden="true" {...props} />
}
