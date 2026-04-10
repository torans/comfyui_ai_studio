import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { useStore } from "./store";

vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(async (command: string) => {
    if (command === "api_get_workflows") {
      return { data: [] };
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

describe("Settings view", () => {
  beforeEach(() => {
    localStorage.clear();
    useStore.setState({
      token: "token-123",
      user: {
        id: 1,
        name: "测试员工1",
        email: "employee1@test.com",
        role: "staff",
      },
      isAuthenticated: true,
      workflows: [
        {
          id: 1,
          name: "图生图",
          code: "i2i_1",
          type: "i2i",
          version: "1.0.0",
          is_active: true,
          category: "image",
          category_label: "图像",
          description: "图生图",
          parameter_schema: [],
        },
      ],
      selectedWorkflowId: 1,
      jobs: [],
      currentJobId: null,
      serverUrl: "http://admin.test",
      isConnected: false,
      checkpoints: [],
      selectedModel: "",
    });
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it("renders enriched settings content with version, support info and logout button", async () => {
    const { container } = render(<App />);

    const settingsButton = container.querySelector(".sidebar-footer .cat-item") as HTMLButtonElement | null;
    expect(settingsButton).not.toBeNull();
    fireEvent.click(settingsButton!);

    expect(await screen.findByRole("heading", { name: "设置中心" })).toBeInTheDocument();
    expect(screen.getByText("测试员工1")).toBeInTheDocument();
    expect(screen.getByText("employee1@test.com")).toBeInTheDocument();
    expect(screen.getByText("v0.1.0")).toBeInTheDocument();
    expect(screen.getByText("兰秋十六")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: "https://lanqiu.tech" })).toHaveAttribute("href", "https://lanqiu.tech");
    expect(screen.getByRole("button", { name: /退出登录/i })).toBeInTheDocument();
  });
});
