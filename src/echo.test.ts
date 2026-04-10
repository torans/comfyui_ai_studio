import { describe, expect, it } from "vitest";
import {
  buildReverbConfig,
  getPrivateUserChannelName,
  JOB_STATUS_EVENT_NAME,
} from "./echo";

describe("echo config", () => {
  it("builds a Reverb config that authenticates private channels with bearer token", () => {
    expect(
      buildReverbConfig("token-123", "http://admin.test", {
        wsHost: "admin.test",
        wsPort: 8080,
        forceTLS: false,
      }),
    ).toMatchObject({
      broadcaster: "reverb",
      key: "beikuman_key",
      wsHost: "admin.test",
      wsPort: 8080,
      wssPort: 8080,
      forceTLS: false,
      enabledTransports: ["ws", "wss"],
      authEndpoint: "http://admin.test/broadcasting/auth",
      auth: {
        headers: {
          Authorization: "Bearer token-123",
          Accept: "application/json",
        },
      },
    });
  });

  it("uses the expected private user channel and Laravel custom event name", () => {
    expect(getPrivateUserChannelName(12)).toBe("user.12");
    expect(JOB_STATUS_EVENT_NAME).toBe(".generation-job.status-changed");
  });
});
