import test from "node:test";
import assert from "node:assert/strict";
import { queueComfyPrompt } from "../src/comfy.ts";

test("queueComfyPrompt posts prompt payload to /prompt", async () => {
  const calls = [];
  globalThis.fetch = async (url, init) => {
    calls.push({ url, init });
    return {
      ok: true,
      json: async () => ({ prompt_id: "abc123" }),
    };
  };

  const result = await queueComfyPrompt("https://example.com/", { foo: "bar" }, "client-1");

  assert.equal(result.prompt_id, "abc123");
  assert.equal(calls[0].url, "https://example.com/prompt");
  assert.equal(calls[0].init.method, "POST");
  assert.equal(
    calls[0].init.body,
    JSON.stringify({ prompt: { foo: "bar" }, client_id: "client-1" }),
  );
});

test("queueComfyPrompt surfaces backend error text", async () => {
  globalThis.fetch = async () => ({
    ok: false,
    status: 400,
    text: async () => "invalid workflow",
  });

  await assert.rejects(
    () => queueComfyPrompt("https://example.com", { foo: "bar" }, "client-1"),
    /ComfyUI 返回 400: invalid workflow/,
  );
});
