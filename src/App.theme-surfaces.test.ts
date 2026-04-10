import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("app theme surfaces", () => {
  it("uses theme variables for major backgrounds instead of hard-coded light colors", () => {
    const source = readFileSync(resolve(__dirname, "App.css"), "utf8");

    expect(source).toContain("background: var(--bg-primary)");
    expect(source).toContain("background: var(--bg-secondary)");
    expect(source).toContain("background: var(--surface-primary)");
    expect(source).toContain("background: var(--surface-secondary)");
  });
});
