import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("workflow job fallback logic", () => {
  it("keeps a direct job refresh fallback in the workflow view", () => {
    const source = readFileSync(resolve(__dirname, "App.tsx"), "utf8");

    expect(source).toContain("const latestJob = await generationJobs.get(serverUrl, token, currentJobId);");
    expect(source).toContain("const timer = window.setInterval(refreshJob, 2000);");
    expect(source).toContain('if (currentJob?.status === "succeeded" || currentJob?.status === "failed") return;');
  });
});
