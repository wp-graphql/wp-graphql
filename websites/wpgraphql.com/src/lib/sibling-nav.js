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
