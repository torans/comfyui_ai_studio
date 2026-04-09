interface QueuePromptResponse {
  prompt_id: string;
}

export async function queueComfyPrompt(
  serverUrl: string,
  prompt: Record<string, any>,
  clientId: string,
): Promise<QueuePromptResponse> {
  const baseUrl = serverUrl.replace(/\/+$/, "");
  const response = await fetch(`${baseUrl}/prompt`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      prompt,
      client_id: clientId,
    }),
  });

  if (!response.ok) {
    const errorText = await response.text().catch(() => "未知错误");
    throw new Error(`ComfyUI 返回 ${response.status}: ${errorText}`);
  }

  return response.json();
}
