import dynamic from "next/dynamic"

/**
 * This component allows an icon to be rendered based on the name of the icon (i.e., string passed from a WordPress Nav Menu Item)
 * @see: https://github.com/tailwindlabs/heroicons/issues/278#issuecomment-851594776
 */
export default function DynamicHeroIcon(props) {
  // const icon_path = `@heroicons/react/24/outline/esm/${props.icon}.js`
  // console.log("icon Path: ", icon_path)

  const Icon = dynamic(() =>
    import(`@heroicons/react/24/outline/esm`).then(icons => icons[props.icon])
  )

  return <Icon className="h-6 w-6 text-white" aria-hidden="true" {...props}/>
}
