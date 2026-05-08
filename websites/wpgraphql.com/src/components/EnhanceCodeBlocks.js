import { useEffect } from "react"
import { useRouter } from "next/router"

/**
 * Enhances every `<pre>` block under `.prose` (the docs MDX renderer and the
 * dangerouslySetInnerHTML containers used by WP-driven posts/recipes) with
 * a "code window" treatment: a top header bar with traffic-light dots, a
 * language label, and a Copy button.
 *
 * Implementation note: we use vanilla DOM rather than rendering a React
 * tree because the prose containers receive their content via
 * dangerouslySetInnerHTML (server-rendered HTML from WP), so React can't
 * inject components into them. We re-run on route changes by depending on
 * `asPath` and skip already-enhanced <pre>s via a data attribute.
 */
const ENHANCED_FLAG = "data-pre-enhanced"

const COPY_ICON_SVG = `
<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <rect width="14" height="14" x="8" y="8" rx="2" ry="2"></rect>
  <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>
</svg>`

const CHECK_ICON_SVG = `
<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <polyline points="20 6 9 17 4 12"></polyline>
</svg>`

function detectLang(codeEl) {
  if (!codeEl) return ""
  const cls = Array.from(codeEl.classList).find((c) => c.startsWith("language-"))
  if (!cls) return ""
  return cls.replace(/^language-/, "")
}

function enhancePre(pre) {
  if (!pre || pre.hasAttribute(ENHANCED_FLAG)) return
  pre.setAttribute(ENHANCED_FLAG, "true")

  const codeEl = pre.querySelector("code")
  const lang = detectLang(codeEl)

  // Wrap the <pre> in a code-block container that hosts the header.
  const wrap = document.createElement("div")
  wrap.className = "code-block-wrap not-prose"
  pre.parentNode.insertBefore(wrap, pre)

  const header = document.createElement("div")
  header.className = "code-block-header"
  header.innerHTML = `
    <div class="code-block-dots" aria-hidden="true">
      <span class="code-block-dot" style="background:#FF5F57"></span>
      <span class="code-block-dot" style="background:#FEBC2E"></span>
      <span class="code-block-dot" style="background:#28C840"></span>
    </div>
    ${lang ? `<span class="code-block-lang">${lang}</span>` : ""}
    <button type="button" class="code-block-copy" aria-label="Copy code">
      ${COPY_ICON_SVG}<span class="code-block-copy-label">Copy</span>
    </button>
  `

  const copyBtn = header.querySelector(".code-block-copy")
  copyBtn.addEventListener("click", async () => {
    const text = (codeEl?.textContent ?? pre.textContent ?? "").replace(/\n$/, "")
    try {
      await navigator.clipboard.writeText(text)
      copyBtn.classList.add("is-copied")
      copyBtn.innerHTML = `${CHECK_ICON_SVG}<span class="code-block-copy-label">Copied</span>`
      setTimeout(() => {
        copyBtn.classList.remove("is-copied")
        copyBtn.innerHTML = `${COPY_ICON_SVG}<span class="code-block-copy-label">Copy</span>`
      }, 1500)
    } catch {
      copyBtn.classList.add("is-error")
      copyBtn.querySelector(".code-block-copy-label").textContent = "Failed"
    }
  })

  wrap.appendChild(header)
  wrap.appendChild(pre)
}

export default function EnhanceCodeBlocks() {
  const { asPath } = useRouter()

  useEffect(() => {
    if (typeof window === "undefined") return
    // Defer to the next frame so React has finished committing.
    const id = requestAnimationFrame(() => {
      const pres = document.querySelectorAll(".prose pre")
      pres.forEach(enhancePre)
    })
    return () => cancelAnimationFrame(id)
  }, [asPath])

  return null
}
