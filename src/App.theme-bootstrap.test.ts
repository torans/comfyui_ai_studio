import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("app theme bootstrap", () => {
  it("initializes theme handling at the app root", () => {
    const source = readFileSync(resolve(__dirname, "App.tsx"), "utf8");

    expect(source).toContain('import { useTheme } from "./hooks/useTheme"');
    expect(source).toContain("useTheme();");
  });
});
