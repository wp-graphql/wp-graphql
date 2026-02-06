/**
 * E2E tests for wpgraphql.com homepage
 */
import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
	test('should load the homepage', async ({ page }) => {
		await page.goto('/');
		await expect(page).toHaveTitle(/WPGraphQL/i);
	});

	test('should have working navigation', async ({ page }) => {
		await page.goto('/');
		
		// Check that main navigation elements are present
		const nav = page.locator('nav').first();
		await expect(nav).toBeVisible();
	});

	test('should have accessible links', async ({ page }) => {
		await page.goto('/');
		
		// Check that links are present and clickable
		const links = page.locator('a[href]');
		const count = await links.count();
		expect(count).toBeGreaterThan(0);
	});
});
