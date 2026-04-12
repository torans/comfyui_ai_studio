import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("version bump script", () => {
  it("keeps a single command that updates the Tauri version files together", () => {
    const packageJson = readFileSync(resolve(__dirname, "../package.json"), "utf8");
    const script = readFileSync(resolve(__dirname, "../scripts/bump-version.mjs"), "utf8");

    expect(packageJson).toContain('"bump-version": "node scripts/bump-version.mjs"');
    expect(script).toContain('src-tauri/Cargo.toml');
    expect(script).toContain('src-tauri/tauri.conf.json');
    expect(script).toContain('package.json');
  });
});
