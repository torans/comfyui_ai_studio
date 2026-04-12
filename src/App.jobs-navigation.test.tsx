import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { useStore } from "./store";

const makeJob = (id: number) => ({
  id,
  type: "t2i",
  status: "succeeded",
  workflow_template_id: 1,
  workflow_name: `任务 ${id}`,
  created_at: "2026-04-10T10:00:00.000Z",
  updated_at: "2026-04-10T10:01:00.000Z",
  assets: [
    {
      id: 1000 + id,
      type: "output",
      media_kind: "image" as const,
      filename: `result-${id}.png`,
      url: `https://example.com/result-${id}.png`,
    },
  ],
});

vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(async (command: string, payload?: { page?: number }) => {
    if (command === "api_get_workflows") {
      return {
        data: [
          {
            id: 1,
            name: "图像生成",
            code: "image_1",
            type: "t2i",
            version: "1.0.0",
            is_active: true,
            category: "image",
            category_label: "文生图",
            description: "图片工作流",
            parameter_schema: [],
          },
        ],
      };
    }

    if (command === "api_comfyui_system_stats") {
      return { online: true };
    }

    if (command === "api_get_jobs") {
      const page = payload?.page ?? 1;
      if (page === 1) {
        return {
          data: Array.from({ length: 40 }, (_, index) => makeJob(index + 1)),
          meta: { current_page: 1, last_page: 1 },
        };
      }

      return {
        data: [],
        meta: { current_page: page, last_page: 1 },
      };
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

describe("jobs navigation and rendering", () => {
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

  it("returns from jobs to workflow mode through the topbar action", async () => {
    render(<App />);

    fireEvent.click(await screen.findByRole("button", { name: "任务记录" }));
    expect(await screen.findByRole("heading", { name: "任务记录" })).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "创作" }));

    await waitFor(() => {
      expect(screen.getByRole("navigation", { name: "工作流浏览" })).toBeInTheDocument();
    });
  });

  it("renders jobs in batches instead of mounting every loaded card at once", async () => {
    render(<App />);

    fireEvent.click(await screen.findByRole("button", { name: "任务记录" }));

    await waitFor(() => {
      expect(screen.getAllByTestId("job-card")).toHaveLength(24);
    });

    expect(screen.queryByText("任务 40")).not.toBeInTheDocument();
  });
});
