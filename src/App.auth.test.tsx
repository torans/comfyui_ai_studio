import { act, cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import App from "./App";
import { useStore } from "./store";

vi.mock("@tauri-apps/api/core", () => ({
  invoke: vi.fn(async (command: string) => {
    if (command === "get_comfy_models") {
      return ["mock-model.safetensors"];
    }

    if (command === "api_login") {
      return {
        token: "token-123",
        user: {
          id: 1,
          name: "Test User",
          email: "test@example.com",
          role: "staff",
        },
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

class MockWebSocket {
  static OPEN = 1;
  readyState = MockWebSocket.OPEN;
  onopen: ((event: Event) => void) | null = null;
  onmessage: ((event: MessageEvent) => void) | null = null;
  onerror: ((event: Event) => void) | null = null;
  onclose: ((event: CloseEvent) => void) | null = null;

  constructor(_url: string) {}

  send() {}

  close() {
    this.onclose?.(new CloseEvent("close"));
  }
}

describe("App auth flow", () => {
  afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  beforeEach(() => {
    localStorage.clear();
    useStore.setState({
      token: null,
      user: null,
      isAuthenticated: false,
      serverUrl: "http://admin.test",
      isConnected: false,
      checkpoints: [],
      selectedModel: "",
    });

    vi.stubGlobal("fetch", vi.fn(async () => ({
      ok: true,
      blob: async () => new Blob(),
      json: async () => ({}),
      text: async () => "",
    })));
    vi.stubGlobal("WebSocket", MockWebSocket);
    vi.stubGlobal("alert", vi.fn());
    vi.spyOn(console, "error").mockImplementation(() => {});
  });

  it("renders after login and logout without hook order errors", async () => {
    const view = render(<App />);

    expect(await screen.findByRole("button", { name: "登录" })).toBeInTheDocument();

    await act(async () => {
      useStore.getState().login("token-123", {
        id: 1,
        name: "Test User",
        email: "test@example.com",
        role: "staff",
      });
    });

    await waitFor(() => {
      expect(screen.getByTitle("退出登录")).toBeInTheDocument();
    });

    expect(console.error).not.toHaveBeenCalledWith(
      expect.stringContaining("Rendered more hooks than during the previous render"),
    );

    await act(async () => {
      useStore.getState().logout();
    });

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "登录" })).toBeInTheDocument();
    });

    view.unmount();
  });
});
