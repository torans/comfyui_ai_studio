import { cleanup, render, screen, within } from "@testing-library/react";
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
            name: "产品融合一",
            code: "merge_1",
            type: "i2i",
            version: "1.0.5",
            is_active: true,
            category: "i2i",
            category_label: "图生图",
            description: "多图融合工作流",
            parameter_schema: [],
          },
          {
            id: 2,
            name: "单图编辑一",
            code: "edit_1",
            type: "i2i",
            version: "2.1.0",
            is_active: true,
            category: "i2i",
            category_label: "图生图",
            description: "单图修饰工作流",
            parameter_schema: [],
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

describe("workflow browser layout", () => {
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
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it("keeps workflow versions out of the browser cards and shows them in the detail header", async () => {
    render(<App />);

    const browser = await screen.findByRole("navigation", { name: "工作流浏览" });
    expect(within(browser).queryByText("1.0.5")).not.toBeInTheDocument();
    expect(within(browser).queryByText("2.1.0")).not.toBeInTheDocument();

    expect(await screen.findByText("v1.0.5")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "任务记录" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "设置" })).toBeInTheDocument();
  });
});
