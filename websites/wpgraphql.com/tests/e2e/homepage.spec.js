/**
 * E2E tests for wpgraphql.com homepage
 */
import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
	test('should load the homepage', async ({ page }) => {
		// Navigate to homepage with longer timeout
		const response = await page.goto('/', { 
			waitUntil: 'domcontentloaded', 
			timeout: 30000 
		});
		
		// Check that we got a valid response
		expect(response?.status()).toBe(200);
		
		// Wait for page to be fully loaded
		await page.waitForLoadState('networkidle', { timeout: 30000 });
		
		// Wait a bit more for any client-side rendering
		await page.waitForTimeout(2000);
		
		// Check that the page has loaded (has body content)
		const body = page.locator('body');
		await expect(body).toBeVisible({ timeout: 10000 });
		
		// Get the actual title for debugging
		const title = await page.title();
		console.log('Page title:', title);
		console.log('Page URL:', page.url());
		
		// Check page title contains WPGraphQL (with fallback if title is empty)
		if (title && title.trim() !== '') {
			await expect(page).toHaveTitle(/WPGraphQL/i, { timeout: 5000 });
		} else {
			// If title is empty, check for WPGraphQL in the page content instead
			const pageContent = await page.textContent('body');
			console.log('Page content preview:', pageContent?.substring(0, 200));
			expect(pageContent).toContain('WPGraphQL');
		}
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
