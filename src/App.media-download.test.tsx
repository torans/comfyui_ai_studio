import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { invoke } from "@tauri-apps/api/core";
import App from "./App";
import { useStore } from "./store";

vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(async (command: string) => {
    if (command === "api_get_workflows") {
      return {
        data: [
          {
            id: 1,
            name: "图片生成",
            code: "image_1",
            type: "t2i",
            version: "1.0.0",
            is_active: true,
            category: "image",
            category_label: "图像",
            description: "图片工作流",
            parameter_schema: [],
          },
        ],
      };
    }

    if (command === "api_comfyui_system_stats") {
      return { online: true };
    }

    if (command === "api_create_job") {
      return { job_id: 201, status: "queued" };
    }

    if (command === "api_download_remote_media") {
      return { saved_path: "/Users/test/Downloads/Beikuman AI Studio/result-image.png" };
    }

    return null;
  }),
}));

vi.mock("@tauri-apps/api/window", () => ({
  getCurrentWindow: () => ({
    startDragging: vi.fn(),
  }),
}));

vi.mock("pusher-js", () => ({
  default: class MockPusher {
    connection = { state: "connected" };
    subscribe() {
      return {
        bind: vi.fn(),
        unbind: vi.fn(),
      };
    }
    unsubscribe() {}
    disconnect() {}
  },
}));

describe("Media download in workflow view", () => {
  beforeEach(() => {
    localStorage.clear();
    useStore.setState({
      token: "token-123",
      user: {
        id: 1,
        name: "Test User",
        email: "test@example.com",
        role: "staff",
      },
      isAuthenticated: true,
      workflows: [],
      selectedWorkflowId: null,
      jobs: [
        {
          id: 201,
          type: "t2i",
          status: "succeeded",
          workflow_template_id: 1,
          workflow_name: "图片生成",
          created_at: "2026-04-10T10:00:00.000Z",
          updated_at: "2026-04-10T10:01:00.000Z",
          assets: [
            {
              id: 301,
              type: "output",
              media_kind: "image",
              filename: "result-image.png",
              url: "https://example.com/result-image.png",
            },
          ],
        },
      ],
      currentJobId: null,
      serverUrl: "http://admin.test",
      isConnected: false,
      checkpoints: [],
      selectedModel: "",
    });

    vi.stubGlobal("alert", vi.fn());
    vi.stubGlobal("fetch", vi.fn(async () => ({
      ok: true,
      blob: async () => new Blob(),
      json: async () => ({}),
      text: async () => "",
    })));
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("downloads generated images through the Tauri command instead of relying on browser download", async () => {
    render(<App />);

    const generateButton = await screen.findByRole("button", { name: /开始生成/i });
    fireEvent.click(generateButton);

    const saveButton = await screen.findByRole("button", { name: "保存图片" });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(invoke).toHaveBeenCalledWith("api_download_remote_media", {
        url: "https://example.com/result-image.png",
        suggestedFilename: "result-image.png",
      });
    });
  });
});
