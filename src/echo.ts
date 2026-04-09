import Echo from "laravel-echo";
import Pusher from "pusher-js";

const REVERB_KEY = "beikuman_key";
const DEFAULT_REVERB_PORT = 8080;

export const JOB_STATUS_EVENT_NAME = ".generation-job.status-changed";

type EchoInstance = Echo<"reverb">;

type ReverbConfigOverrides = {
  wsHost?: string;
  wsPort?: number;
  forceTLS?: boolean;
};

export type JobStatusPayload = {
  id: number;
  status: string;
  type: string;
  workflow_code?: string;
  workflow_name?: string;
  error_message?: string;
  started_at?: string;
  finished_at?: string;
  progress?: number;
  assets?: any[];
  input_json?: any;
};

let echoInstance: EchoInstance | null = null;

function trimTrailingSlash(url: string): string {
  return url.replace(/\/+$/, "");
}

function getHostFromUrl(url: string): string {
  try {
    const parsed = new URL(url);
    return parsed.hostname;
  } catch (e) {
    return "127.0.0.1";
  }
}

function isHttps(url: string): boolean {
  try {
    return new URL(url).protocol === "https:";
  } catch (e) {
    return false;
  }
}

export function getPrivateUserChannelName(userId: number): string {
  return `user.${userId}`;
}

export function buildReverbConfig(token: string, apiBaseUrl: string, overrides: ReverbConfigOverrides = {}) {
  const host = getHostFromUrl(apiBaseUrl);
  const useHttps = isHttps(apiBaseUrl);
  
  const wsHost = overrides.wsHost ?? host;
  const wsPort = overrides.wsPort ?? DEFAULT_REVERB_PORT;
  const forceTLS = overrides.forceTLS ?? useHttps;

  return {
    broadcaster: "reverb" as const,
    key: REVERB_KEY,
    cluster: "mt1",
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS,
    enabledTransports: ["ws", "wss"] as Array<"ws" | "wss">,
    authEndpoint: `${trimTrailingSlash(apiBaseUrl)}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    },
  };
}

export function getEcho(token: string, apiBaseUrl: string): EchoInstance {
  const existingSocket = (echoInstance as any)?.connector?.pusher?.connection;
  
  // 如果连接参数变了，我们需要强制重新连接
  if (echoInstance && existingSocket?.state === "connected") {
    // 检查 host 是否匹配，如果不匹配则断开重连
    const currentOptions = (echoInstance as any).options;
    const targetHost = getHostFromUrl(apiBaseUrl);
    if (currentOptions.wsHost !== targetHost) {
      echoInstance.disconnect();
    } else {
      return echoInstance;
    }
  }

  if (echoInstance) {
    echoInstance.disconnect();
  }

  (window as typeof window & { Pusher?: typeof Pusher }).Pusher = Pusher;
  Pusher.logToConsole = true;

  const config = buildReverbConfig(token, apiBaseUrl);
  console.log('🌍 正在初始化 Echo:', { 
    API: apiBaseUrl, 
    WSHost: config.wsHost, 
    WSPort: config.wsPort,
    TLS: config.forceTLS
  });
  
  echoInstance = new Echo(config);

  return echoInstance;
}

export function disconnectEcho(): void {
  if (!echoInstance) {
    return;
  }

  echoInstance.disconnect();
  echoInstance = null;
}

export function listenToJobStatus(
  token: string,
  userId: number,
  apiBaseUrl: string,
  callback: (data: JobStatusPayload) => void,
): () => void {
  const echo = getEcho(token, apiBaseUrl);
  const channelName = getPrivateUserChannelName(userId);
  const channel = echo.private(channelName);

  channel.listen(JOB_STATUS_EVENT_NAME, (data: unknown) => {
    callback(data as JobStatusPayload);
  });

  return () => {
    channel.stopListening(JOB_STATUS_EVENT_NAME);
    echo.leaveChannel(`private-${channelName}`);
  };
}
