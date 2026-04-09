import test from "node:test";
import assert from "node:assert/strict";
import workflow from "../src/workflows/video_ltx2_3_i2v.json" with { type: "json" };
import { extractVideoUrlsFromHistory, prepareI2VWorkflow } from "../src/i2v.ts";
import { readFileSync } from "node:fs";

test("prepareI2VWorkflow injects uploaded image, prompt, ratio, and fresh seeds", () => {
  const prepared = prepareI2VWorkflow(workflow, {
    uploadedImageName: "input/foo.png",
    prompt: "一位未来感女性在风中缓慢回头，镜头轻轻推进",
    aspectRatio: { w: 1024, h: 1024 },
    seed: 123456,
  });

  assert.equal(prepared["269"].inputs.image, "input/foo.png");
  assert.equal(prepared["267:266"].inputs.value, "一位未来感女性在风中缓慢回头，镜头轻轻推进");
  assert.equal(prepared["267:257"].inputs.value, 1024);
  assert.equal(prepared["267:258"].inputs.value, 1024);
  assert.equal(prepared["267:216"].inputs.noise_seed, 123456);
  assert.equal(prepared["267:237"].inputs.noise_seed, 123456);
});

test("extractVideoUrlsFromHistory returns saved video files from history outputs", () => {
  const history = {
    abc123: {
      outputs: {
        "75": {
          gifs: [
            {
              filename: "video/test.mp4",
              subfolder: "",
              type: "output",
            },
          ],
        },
      },
    },
  };

  assert.deepEqual(
    extractVideoUrlsFromHistory(history, "abc123", "https://example.com"),
    ["https://example.com/view?filename=video%2Ftest.mp4&subfolder=&type=output"],
  );
});

test("video polling timeout is long enough for ltx generation", () => {
  const appSource = readFileSync(new URL("../src/App.tsx", import.meta.url), "utf8");

  assert.match(
    appSource,
    /const VIDEO_POLL_TIMEOUT_MS = 45 \* 60 \* 1000;/,
    "ltx i2v polling should allow long-running video jobs",
  );
});
