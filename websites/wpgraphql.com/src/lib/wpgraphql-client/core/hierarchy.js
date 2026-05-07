function slugify(value) {
  if (typeof value !== "string") return null
  const lower = value.toLowerCase().trim()
  if (!lower) return null
  return lower
}

/**
 * Generate sensible kebab/snake/lowered variants of a post type or taxonomy
 * name, so authors can declare templates using either the JS-idiomatic
 * kebab-case form (e.g. "code-snippet") or the lowered-compact form
 * ("codesnippet"), regardless of how WPGraphQL exposes the type name
 * ("CodeSnippet", "codeSnippet", "code_snippet", etc.).
 *
 * Returns an ordered, deduplicated array. Order matters: more-specific
 * variants come first so registries that opt into both forms get the
 * intended one.
 */
function nameVariants(value) {
  if (typeof value !== "string") return []
  const trimmed = value.trim()
  if (!trimmed) return []
  const lower = trimmed.toLowerCase()
  const kebab = trimmed
    .replace(/([a-z0-9])([A-Z])/g, "$1-$2") // camelCase boundary
    .replace(/([A-Z]+)([A-Z][a-z])/g, "$1-$2") // ACRONYM_Word boundary
    .replace(/[_\s]+/g, "-") // snake_case / spaces
    .toLowerCase()
  const out = []
  const seen = new Set()
  for (const v of [kebab, lower]) {
    if (v && !seen.has(v)) {
      seen.add(v)
      out.push(v)
    }
  }
  return out
}

export function buildCandidateNames(seed) {
  const names = []
  if (!seed) return names

  const typename = seed.typename
  const slug = slugify(seed.slug)
  const postTypeVariants = nameVariants(seed.postType)
  const taxonomyVariants = nameVariants(seed.taxonomy)
  const archiveTypeSource = seed.postType ?? seed.name
  const archiveTypeVariants = nameVariants(archiveTypeSource)

  if (seed.isFrontPage) names.push("front-page")
  if (seed.isPostsPage) names.push("home")

  if (typename === "Page") {
    if (slug) names.push(`page-${slug}`)
    names.push("page")
    names.push("singular")
  } else if (typename === "ContentType") {
    for (const v of archiveTypeVariants) names.push(`archive-${v}`)
    names.push("archive")
  } else if (typename === "Category") {
    if (slug) names.push(`category-${slug}`)
    names.push("category")
    names.push("archive")
  } else if (typename === "Tag") {
    if (slug) names.push(`tag-${slug}`)
    names.push("tag")
    names.push("archive")
  } else if (typename === "User") {
    if (slug) names.push(`author-${slug}`)
    names.push("author")
    names.push("archive")
  } else if (typename && taxonomyVariants.length > 0) {
    for (const tax of taxonomyVariants) {
      if (slug) names.push(`taxonomy-${tax}-${slug}`)
      names.push(`taxonomy-${tax}`)
    }
    names.push("taxonomy")
    names.push("archive")
  } else if (postTypeVariants.length > 0) {
    for (const pt of postTypeVariants) {
      if (slug) names.push(`single-${pt}-${slug}`)
      names.push(`single-${pt}`)
    }
    names.push("single")
    names.push("singular")
  } else if (typename) {
    names.push("singular")
  }

  if (!seed.node && !seed.isFrontPage) names.push("404")
  names.push("index")

  return dedupe(names)
}

function dedupe(names) {
  const seen = new Set()
  const out = []
  for (const name of names) {
    if (!seen.has(name)) {
      seen.add(name)
      out.push(name)
    }
  }
  return out
}

export function resolveTemplateName(seed, registry) {
  if (!registry || typeof registry !== "object") {
    throw new TypeError("resolveTemplateName: registry must be an object")
  }
  const candidates = buildCandidateNames(seed)
  for (const name of candidates) {
    if (Object.prototype.hasOwnProperty.call(registry, name) && registry[name]) {
      return name
    }
  }
  throw new Error(
    `resolveTemplateName: no matching template found. Tried: ${candidates.join(", ")}`
  )
}
