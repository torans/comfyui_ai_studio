import test from "node:test";
import assert from "node:assert/strict";
import t2iWorkflow from "../src/workflows/t2i_api.json" with { type: "json" };
import {
  extractImageUrlsFromExecutedMessage,
  extractImageUrlsFromHistory,
  prepareT2IWorkflow,
  workflowSupportsNegativePrompt,
} from "../src/t2i.ts";

test("t2i workflow does not advertise unsupported negative prompt editing", () => {
  assert.equal(workflowSupportsNegativePrompt(t2iWorkflow), false);
});

test("prepareT2IWorkflow updates prompt and latent config", () => {
  const prepared = prepareT2IWorkflow(t2iWorkflow, {
    prompt: "a glass teapot on a stone table",
    negative: "watermark",
    aspectRatio: { w: 768, h: 1024 },
    batchSize: 3,
    seed: 42,
  });

  assert.equal(prepared["69"].inputs.prompt, "a glass teapot on a stone table");
  assert.equal(prepared["64:13"].inputs.width, 768);
  assert.equal(prepared["64:13"].inputs.height, 1024);
  assert.equal(prepared["64:13"].inputs.batch_size, 3);
  assert.equal(prepared["64:3"].inputs.seed, 42);
  assert.equal(prepared["69"].inputs.prompt, "a glass teapot on a stone table");
});

test("executed messages without images do not produce result urls", () => {
  const previewOnlyMessage = {
    type: "executed",
    data: {
      output: {
        preview_text: "some llm generated prompt",
      },
    },
  };

  assert.deepEqual(
    extractImageUrlsFromExecutedMessage(previewOnlyMessage, "https://example.com"),
    [],
  );
});

test("history lookup extracts saved image urls from any output node", () => {
  const history = {
    abc123: {
      outputs: {
        "71": { preview_text: "draft prompt" },
        "63": {
          images: [
            { filename: "foo.png", subfolder: "bar", type: "output" },
          ],
        },
      },
    },
  };

  assert.deepEqual(
    extractImageUrlsFromHistory(history, "abc123", "https://example.com"),
    ["https://example.com/view?filename=foo.png&subfolder=bar&type=output"],
  );
});
