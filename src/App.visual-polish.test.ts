import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("visual polish hooks", () => {
  it("includes the new card, detail, and empty-state polish classes", () => {
    const appSource = readFileSync(resolve(__dirname, "App.tsx"), "utf8");
    const cssSource = readFileSync(resolve(__dirname, "App.css"), "utf8");

    expect(appSource).toContain("workflow-browser-card-accent");
    expect(appSource).toContain("workflow-meta-panel");
    expect(appSource).toContain("empty-state-orbit");

    expect(cssSource).toContain(".workflow-browser-card-accent");
    expect(cssSource).toContain(".workflow-meta-panel");
    expect(cssSource).toContain(".empty-state-orbit");
  });
});
