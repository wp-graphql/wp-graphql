/**
 * E2E tests for the site search (Algolia DocSearch).
 *
 * The DocSearch modal only mounts client-side, so build-time checks never
 * exercise it; these tests are what proves a @docsearch/react upgrade
 * actually works in the browser. Queries hit the live Algolia index, same
 * as the rest of the e2e suite hits live site data.
 */
import { test, expect } from "@playwright/test"

test.describe("Site search", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/", { waitUntil: "domcontentloaded", timeout: 30000 })
    await page.waitForLoadState("networkidle", { timeout: 30000 })
  })

  test("opens the search modal from the header button", async ({ page }) => {
    await page.getByRole("button", { name: "Search" }).click()

    await expect(page.locator(".DocSearch-Modal")).toBeVisible({
      timeout: 10000,
    })
    await expect(page.locator(".DocSearch-Input")).toBeFocused()
  })

  test("opens the search modal with the keyboard shortcut", async ({
    page,
  }) => {
    await page.keyboard.press("ControlOrMeta+k")

    await expect(page.locator(".DocSearch-Modal")).toBeVisible({
      timeout: 10000,
    })
  })

  test("returns results for a query and closes on Escape", async ({ page }) => {
    await page.getByRole("button", { name: "Search" }).click()
    await page.locator(".DocSearch-Input").fill("interface")

    // Results come from the live Algolia index, so allow for network time.
    const hits = page.locator(".DocSearch-Hit a[href]")
    await expect(hits.first()).toBeVisible({ timeout: 15000 })

    // Hits should link somewhere real on the site. Hrefs are absolute
    // production URLs, so assert rather than click (a click would
    // navigate the test away from the server under test).
    const href = await hits.first().getAttribute("href")
    expect(href).toContain("wpgraphql.com")

    await page.keyboard.press("Escape")
    await expect(page.locator(".DocSearch-Modal")).toBeHidden({
      timeout: 10000,
    })
  })
})
