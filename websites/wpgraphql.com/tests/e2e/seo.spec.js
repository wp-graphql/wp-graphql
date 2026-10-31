/**
 * E2E tests for the SEO tags on routes that don't come from WordPress nodes:
 * the hand-authored marketing pages and the markdown-sourced reference routes.
 */
import { test, expect } from "@playwright/test"

const routes = [
  { path: "/community", title: /^Community - WPGraphQL$/ },
  {
    path: "/developer-reference",
    title: /^Developer Reference - WPGraphQL$/,
  },
  { path: "/extensions/wp-graphql-acf", title: /^WPGraphQL for ACF/ },
  { path: "/extensions/wp-graphql-ide", title: /^WPGraphQL IDE/ },
  {
    path: "/extensions/wp-graphql-smart-cache",
    title: /^WPGraphQL Smart Cache/,
  },
  // Titles and descriptions below come from the markdown itself.
  {
    path: "/actions",
    title: /^Actions - WPGraphQL Developer Reference$/,
    description: /^Actions let your code run/,
  },
  {
    path: "/actions/graphql_init",
    title: /^graphql_init - WPGraphQL Developer Reference$/,
    description: /^Fire off init action/,
  },
  {
    path: "/filters/graphql_request_results",
    title: /^graphql_request_results - WPGraphQL Developer Reference$/,
  },
  {
    path: "/functions/deregister_graphql_field",
    title: /^deregister_graphql_field - WPGraphQL Developer Reference$/,
    description: /^Given a Type Name and Field Name/,
  },
  { path: "/recipes", title: /^Recipes - WPGraphQL Developer Reference$/ },
  {
    path: "/recipes/add-field-for-unencoded-content",
    title: /^Add field for unencoded content - WPGraphQL Recipes$/,
    description: /^The following adds a field to the NodeWithContentEditor/,
  },
]

test.describe("SEO tags", () => {
  for (const route of routes) {
    test(`${route.path} has a title, description and canonical`, async ({
      page,
    }) => {
      const response = await page.goto(route.path, {
        waitUntil: "domcontentloaded",
        timeout: 60000,
      })
      expect(response?.status()).toBe(200)

      await expect(page).toHaveTitle(route.title)

      const description = page.locator('meta[name="description"]')
      await expect(description).toHaveAttribute(
        "content",
        route.description ?? /\S/
      )

      const canonical = page.locator('link[rel="canonical"]')
      await expect(canonical).toHaveAttribute(
        "href",
        new RegExp(`${route.path}$`)
      )
      await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
        "content",
        route.title
      )
    })
  }

  test("the 404 page has a title and is not indexed", async ({ page }) => {
    const response = await page.goto("/this-page-does-not-exist-4366", {
      waitUntil: "domcontentloaded",
      timeout: 60000,
    })
    expect(response?.status()).toBe(404)

    await expect(page).toHaveTitle(/^Page not found - WPGraphQL$/)
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
      "content",
      "noindex, follow"
    )
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(0)
  })
})
