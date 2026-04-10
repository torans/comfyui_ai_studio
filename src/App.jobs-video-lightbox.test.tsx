import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
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
            name: "视频生成",
            code: "video_1",
            type: "video",
            version: "1.0.0",
            is_active: true,
            category: "video",
            category_label: "视频",
            description: "视频工作流",
            parameter_schema: [],
          },
        ],
      };
    }

    if (command === "api_comfyui_system_stats") {
      return { online: true };
    }

    if (command === "api_get_jobs") {
      return {
        data: [
          {
            id: 101,
            type: "video",
            status: "succeeded",
            workflow_template_id: 1,
            workflow_name: "视频任务 A",
            created_at: "2026-04-10T10:00:00.000Z",
            updated_at: "2026-04-10T10:01:00.000Z",
            assets: [
              {
                id: 501,
                type: "output",
                media_kind: "video",
                filename: "demo.mp4",
                url: "https://example.com/demo.mp4",
              },
            ],
          },
        ],
        meta: {
          current_page: 1,
          last_page: 1,
        },
      };
    }

    if (command === "api_download_remote_media") {
      return { saved_path: "/Users/test/Downloads/Beikuman AI Studio/demo.mp4" };
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

describe("Jobs video lightbox", () => {
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

  it("opens a video job lightbox with the VideoPlayer controls visible", async () => {
    const { container } = render(<App />);

    const jobsTab = await screen.findByRole("button", { name: /任务记录/i });
    fireEvent.click(jobsTab);

    await waitFor(() => {
      expect(screen.getByText("视频任务 A")).toBeInTheDocument();
    });

    const cardTitle = screen.getByText("视频任务 A");
    const jobCard = cardTitle.closest(".job-card");
    expect(jobCard).not.toBeNull();
    fireEvent.click(jobCard!);

    await waitFor(() => {
      expect(container.querySelector(".lightbox-content.video-mode")).not.toBeNull();
    });

    const lightbox = container.querySelector(".lightbox-content.video-mode") as HTMLElement;
    expect(lightbox.querySelector(".lightbox-video-player")).not.toBeNull();

    const nativeVideo = lightbox.querySelector("video[controls]");
    expect(nativeVideo).not.toBeNull();

    const scoped = within(lightbox);
    const saveButton = scoped.getByRole("button", { name: /保存视频/i });
    expect(saveButton).toBeInTheDocument();

    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(invoke).toHaveBeenCalledWith("api_download_remote_media", {
        url: "https://example.com/demo.mp4",
        suggestedFilename: "demo.mp4",
      });
    });

    await waitFor(() => {
      expect(screen.getByText("保存成功")).toBeInTheDocument();
      expect(screen.getByText("已保存到 /Users/test/Downloads/Beikuman AI Studio/demo.mp4")).toBeInTheDocument();
    });
  });
});
