/**
 * Given an ordered list of `{ slug, label, href }` items and the current
 * slug, return the previous/next neighbors for a "prev / next" footer.
 * Items are sorted alphabetically by label so navigation is predictable
 * across a reference section.
 */
export function siblingNav(items, currentSlug) {
  const list = Array.isArray(items) ? [...items] : []
  list.sort((a, b) => a.label.localeCompare(b.label))
  const index = list.findIndex((item) => item.slug === currentSlug)
  if (index === -1) {
    return { prev: null, next: null }
  }
  return {
    prev: index > 0 ? list[index - 1] : null,
    next: index < list.length - 1 ? list[index + 1] : null,
  }
}

/**
 * Given an ordered list of `{ href, label }` items already in the intended
 * reading order and the current href, return the previous/next neighbors.
 * Unlike `siblingNav`, the order is preserved as-is: the docs sidebar nav
 * defines a deliberate front-to-back sequence, so we walk it in place rather
 * than re-sorting.
 */
export function orderedSiblings(items, currentHref) {
  const list = Array.isArray(items) ? items : []
  const index = list.findIndex((item) => item.href === currentHref)
  if (index === -1) {
    return { prev: null, next: null }
  }
  return {
    prev: index > 0 ? list[index - 1] : null,
    next: index < list.length - 1 ? list[index + 1] : null,
  }
}
