function slugify(value) {
  if (typeof value !== "string") return null
  const lower = value.toLowerCase().trim()
  if (!lower) return null
  return lower
}

export function buildCandidateNames(seed) {
  const names = []
  if (!seed) return names

  const typename = seed.typename
  const slug = slugify(seed.slug)
  const postType = slugify(seed.postType)
  const taxonomy = slugify(seed.taxonomy)

  if (seed.isFrontPage) names.push("front-page")
  if (seed.isPostsPage) names.push("home")

  if (typename === "Page") {
    if (slug) names.push(`page-${slug}`)
    names.push("page")
    names.push("singular")
  } else if (typename === "ContentType") {
    const archiveType = postType ?? slugify(seed.name)
    if (archiveType) names.push(`archive-${archiveType}`)
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
  } else if (typename && taxonomy) {
    if (slug) names.push(`taxonomy-${taxonomy}-${slug}`)
    names.push(`taxonomy-${taxonomy}`)
    names.push("taxonomy")
    names.push("archive")
  } else if (postType) {
    if (slug) names.push(`single-${postType}-${slug}`)
    names.push(`single-${postType}`)
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
