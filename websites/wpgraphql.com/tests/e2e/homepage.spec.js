/**
 * E2E tests for wpgraphql.com homepage
 */
import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
	test('should load the homepage', async ({ page }) => {
		await page.goto('/');
		
		// Wait for page to load
		await page.waitForLoadState('networkidle');
		
		// Check page title contains WPGraphQL
		await expect(page).toHaveTitle(/WPGraphQL/i);
		
		// Check that the page has loaded (has body content)
		const body = page.locator('body');
		await expect(body).toBeVisible();
	});

	test('should have working navigation', async ({ page }) => {
		await page.goto('/');
		await page.waitForLoadState('networkidle');
		
		// Check that main navigation elements are present
		const nav = page.locator('nav').first();
		await expect(nav).toBeVisible({ timeout: 5000 });
	});

	test('should have accessible links', async ({ page }) => {
		await page.goto('/');
		await page.waitForLoadState('networkidle');
		
		// Check that links are present and clickable
		const links = page.locator('a[href]');
		const count = await links.count();
		expect(count).toBeGreaterThan(0);
		
		// Verify at least one link is visible
		const firstLink = links.first();
		await expect(firstLink).toBeVisible();
	});

	test('should have main content area', async ({ page }) => {
		await page.goto('/');
		await page.waitForLoadState('networkidle');
		
		// Check for main content (could be main tag, article, or div with content)
		const mainContent = page.locator('main, article, [role="main"]').first();
		await expect(mainContent).toBeVisible({ timeout: 5000 });
	});
});
