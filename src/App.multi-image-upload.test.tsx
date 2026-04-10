import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
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
            name: "[Flux] 多图编辑",
            code: "flux_multi_edit",
            type: "i2i",
            version: "1.0.0",
            is_active: true,
            category: "image",
            category_label: "图生图",
            description: "双图参考编辑",
            parameter_schema: [
              {
                node: "11",
                field: "image",
                label: "参考图一",
                type: "image",
                default: "",
              },
              {
                node: "12",
                field: "image",
                label: "参考图二",
                type: "image",
                default: "",
              },
            ],
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

describe("multi image upload fields", () => {
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
    vi.stubGlobal(
      "URL",
      Object.assign(URL, {
        createObjectURL: vi.fn((file: File) => `blob:${file.name}`),
        revokeObjectURL: vi.fn(),
      })
    );
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("keeps previews isolated when schema reuses the same field name", async () => {
    render(<App />);

    const inputs = await screen.findAllByLabelText(/参考图[一二]/);
    expect(inputs).toHaveLength(2);

    const firstFile = new File(["first"], "first.png", { type: "image/png" });
    fireEvent.change(inputs[0], { target: { files: [firstFile] } });

    await waitFor(() => {
      expect(screen.getAllByAltText("first.png")).toHaveLength(1);
    });

    expect(screen.getAllByAltText("first.png")).toHaveLength(1);
    expect(screen.queryByText("参考图二")).toBeInTheDocument();
  });
});
