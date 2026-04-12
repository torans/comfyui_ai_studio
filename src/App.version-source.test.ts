import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("app version source", () => {
  it("reads the app version from Tauri metadata instead of hardcoding it in the UI", () => {
    const source = readFileSync(resolve(__dirname, "App.tsx"), "utf8");

    expect(source).toContain('import { getVersion } from "@tauri-apps/api/app";');
    expect(source).toContain("const FALLBACK_APP_VERSION = packageJson.version;");
    expect(source).toContain("getVersion()");
    expect(source).not.toContain('const APP_VERSION = "');
  });
});
