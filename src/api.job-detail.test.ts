import { describe, expect, it, vi } from "vitest";

const invokeMock = vi.fn();

vi.mock("@tauri-apps/api/core", () => ({
  invoke: (command: string, payload?: Record<string, unknown>) => invokeMock(command, payload),
}));

describe("generation job detail normalization", () => {
  it("maps the detail endpoint shape into the GenerationJob shape used by the app", async () => {
    invokeMock.mockResolvedValueOnce({
      job_id: 134,
      workflow_id: 6,
      workflow_name: "通用文生图二",
      workflow_code: "t2i_tongyong_2",
      category: "t2i",
      status: "succeeded",
      created_at: "2026-04-12T06:56:50.000Z",
      finished_at: "2026-04-12T06:56:56.000Z",
      progress: 100,
      message: "完成",
      assets: [
        {
          id: 120,
          type: "output",
          media_kind: "image",
          filename: "z-image-turbo_00042_.png",
          url: "http://192.168.1.16:8188/view?filename=z-image-turbo_00042_.png&subfolder=&type=output",
        },
      ],
    });

    const { generationJobs } = await import("./api");
    const result = await generationJobs.get("http://192.168.1.16", "token-123", 134);

    expect(result).toMatchObject({
      id: 134,
      workflow_template_id: 6,
      workflow_name: "通用文生图二",
      workflow_code: "t2i_tongyong_2",
      type: "t2i",
      status: "succeeded",
      progress: 100,
      message: "完成",
    });
    expect(result.assets?.[0]?.url).toContain("z-image-turbo_00042_.png");
  });
});
