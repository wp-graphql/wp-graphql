export function flatListToHierarchical(
  data = [],
  { idKey = "id", parentKey = "parentId", childrenKey = "children" } = {}
) {
  const tree = []
  const childrenOf = {}

  data.forEach((item) => {
    const newItem = { ...item }
    const { [idKey]: id, [parentKey]: parentId = 0 } = newItem

    childrenOf[id] = childrenOf[id] || []
    newItem[childrenKey] = childrenOf[id]

    parentId
      ? (childrenOf[parentId] = childrenOf[parentId] || []).push(newItem)
      : tree.push(newItem)
  })

  return tree
}

export function getIconNameFromMenuItem(menuItem) {
  return menuItem?.cssClasses
    ?.find((className) => className.startsWith("icon-"))
    ?.replace("icon-", "")
}
