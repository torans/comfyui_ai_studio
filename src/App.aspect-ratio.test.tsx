import { act, cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { useStore } from "./store";

vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(async (command: string) => {
    if (command === "api_get_workflows") {
      return {
        data: [
          {
            id: 1,
            name: "通用文生图",
            code: "t2i_1",
            type: "t2i",
            version: "1.0.0",
            is_active: true,
            category: "image",
            category_label: "图像",
            description: "通用文生图",
            parameter_schema: {
              aspect_ratio: {
                field: "aspect_ratio",
                label: "尺寸比例",
                type: "select",
                default: "1:1",
                options: {
                  "1:1": "1:1 方图",
                  "16:9": "16:9 横版",
                },
              },
            },
          },
        ],
      };
    }

    if (command === "api_comfyui_system_stats") {
      return { online: true };
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

describe("aspect ratio field", () => {
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
      jobs: [],
      currentJobId: null,
      serverUrl: "http://admin.test",
      isConnected: false,
      checkpoints: [],
      selectedModel: "",
    });
    vi.stubGlobal("alert", vi.fn());
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("renders select schema fields as a combobox", async () => {
    render(<App />);

    await waitFor(() => {
      expect(screen.getByRole("combobox", { name: "尺寸比例" })).toBeInTheDocument();
    });

    expect(screen.getByRole("option", { name: "1:1 方图" })).toBeInTheDocument();
    expect(screen.getByRole("option", { name: "16:9 横版" })).toBeInTheDocument();
  });
});
