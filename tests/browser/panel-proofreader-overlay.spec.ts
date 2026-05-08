import { expect, test } from "@playwright/test";

const PANEL_EMAIL =
  process.env.KIRBY_USER_EMAIL ?? "admin@kirby-proofreader.test";
const PANEL_PASSWORD = process.env.KIRBY_USER_PASSWORD ?? "playwright";

async function login(page) {
  await page.goto("/panel/login");
  await page.getByLabel("Email").fill(PANEL_EMAIL);
  await page.getByLabel("Password").fill(PANEL_PASSWORD);
  await page.getByRole("button", { name: "Log in" }).click();
  await expect(page).toHaveURL(/\/panel/);
}

test.describe("Proofreader review dialog", () => {
  test("opens the review dialog from the site view", async ({ page }) => {
    await login(page);
    await page.route("**/kirby-proofreader/site/optimize", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          status: "ok",
          preview: true,
          rules: ["ellipsis"],
          availableRules: [{ name: "ellipsis", label: "Ellipsis" }],
          suggestions: [
            {
              id: "description:ellipsis:0",
              field: "description",
              fieldLabel: "Description",
              rule: "ellipsis",
              pathLabel: "Description",
              previewBefore: "Wait...",
              previewAfter: "Wait…",
            },
          ],
          changedFields: ["description"],
          diffs: {
            description: {
              from: "Wait...",
              to: "Wait…",
            },
          },
        }),
      });
    });
    await page.goto("/panel/site");

    await page.locator('[title="Optimize typography"]').first().click();
    await expect(
      page.getByRole("heading", { name: "Review typography suggestions" })
    ).toBeVisible();
    await expect(page.locator(".proofreader-review-summary")).toHaveText(
      "1 of 1 suggestion in 1 field."
    );
    await expect(
      page.locator(".proofreader-review-field").filter({ hasText: "Description" })
    ).toBeVisible();
  });

  test("opens the page review dialog without post-apply field overlays", async ({ page }) => {
    await login(page);
    await page.goto("/panel/pages/editorial-review");

    const subtitleInput = page.getByLabel("Subtitle");
    const summaryTextarea = page.getByLabel("Summary");

    await expect(subtitleInput).toBeVisible();
    await expect(summaryTextarea).toBeVisible();

    await page.locator('[title="Optimize typography"]').first().click();
    await expect(
      page.getByRole("heading", { name: "Review typography suggestions" })
    ).toBeVisible();
    const reviewSummary = page.locator(".proofreader-review-summary");
    await expect(reviewSummary).toContainText(
      /of \d+ suggestions? in \d+ fields?\./
    );
    await expect(
      page.getByRole("button", { name: "Apply all fixes and save title" })
    ).toBeVisible();

    const titleGroup = page
      .locator(".proofreader-review-field")
      .filter({ hasText: "Title" })
      .first();
    await expect(titleGroup).toBeVisible();
    await expect(titleGroup.locator("input").first()).toBeChecked();
    await expect(
      titleGroup.getByRole("button", { name: "Apply and save" })
    ).toBeVisible();
    await expect(titleGroup.getByText("1/1")).toBeVisible();
    await titleGroup.locator("input").first().uncheck();
    await expect(reviewSummary).toContainText(
      /of \d+ suggestions? in \d+ fields?\./
    );

    await page.setViewportSize({ width: 390, height: 800 });
    const compactDialogBox = await page.locator(".k-dialog").boundingBox();
    expect(compactDialogBox?.width).toBeGreaterThan(360);
    expect(compactDialogBox?.x).toBeGreaterThanOrEqual(0);
    const compactDialogRight =
      (compactDialogBox?.x ?? 0) + (compactDialogBox?.width ?? 0);
    expect(compactDialogRight).toBeLessThanOrEqual(390);
    await page.setViewportSize({ width: 1280, height: 720 });

    const unicodeRule = page
      .locator(".proofreader-rule-toggle")
      .filter({ hasText: "Unicode" });
    await expect(unicodeRule.locator("input")).toBeDisabled();
    await expect(unicodeRule.locator("input")).not.toBeChecked();

    await expect(page.locator(".proofreader-input-overlay")).toHaveCount(0);
  });
});
