/**
 * E2E tests for the wpgraphql.com blog archive.
 *
 * /blog resolves through the [...wordpressNode] catch-all, which uses
 * fallback: "blocking" with no prerendered paths, so it only renders on
 * demand. A crash anywhere in the shared server bundle surfaces on these
 * on-demand routes, while build-time prerendered pages keep serving from
 * cache.
 */
import { test, expect } from "@playwright/test"

test.describe("Blog archive", () => {
  test("renders on demand with a list of posts", async ({ page }) => {
    const response = await page.goto("/blog", {
      waitUntil: "domcontentloaded",
      timeout: 30000,
    })

    expect(response?.status()).toBe(200)

    await expect(
      page.getByRole("heading", { name: "Blog", level: 1 })
    ).toBeVisible({ timeout: 10000 })

    // Post previews link to dated permalinks like /2025/02/13/slug/
    const postLinks = page.locator('main a[href*="/20"]')
    expect(await postLinks.count()).toBeGreaterThan(0)
  })
})
