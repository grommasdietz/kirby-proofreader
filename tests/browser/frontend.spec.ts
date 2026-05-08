import { expect, test } from "@playwright/test";

test.describe("Frontend layout", () => {
  test("site title is visible in the header", async ({ page }) => {
    await page.goto("/");

    await expect(page.locator("[data-test='site-title']")).toHaveText("Kirby Playground");
    await expect(page).toHaveTitle("Kirby Playground");
  });

  test("page text blocks render Kirbytext markup", async ({ page }) => {
    await page.goto("/");

    // Kirbytext wraps paragraphs in <p> tags; ensure markup is rendered
    const paragraphs = page.locator("[data-test='page-text'] p");
    await expect(paragraphs).toHaveCount(1);
    await expect(paragraphs.first()).toContainText("playground");
  });
});
