import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("generation progress UX", () => {
  it("does not keep the fake local progress timer in App", () => {
    const source = readFileSync(resolve(__dirname, "App.tsx"), "utf8");

    expect(source).not.toContain("setInterval(() => {");
    expect(source).not.toContain("Math.min(95, prev + 1)");
    expect(source).not.toContain("模拟虚假进度");
  });
});
