export interface WorkflowNode {
  class_type?: string;
  inputs?: Record<string, any>;
}

export type Workflow = Record<string, WorkflowNode>;

interface RatioConfig {
  w: number;
  h: number;
}

interface PrepareT2IWorkflowOptions {
  prompt: string;
  negative: string;
  aspectRatio: RatioConfig;
  batchSize: number;
  seed: number;
}

interface ComfyImage {
  filename: string;
  subfolder?: string;
  type?: string;
}

interface ExecutedMessage {
  type?: string;
  data?: {
    output?: {
      images?: ComfyImage[];
    };
  };
}

export function workflowSupportsNegativePrompt(workflow: Workflow): boolean {
  const samplerNode = Object.values(workflow).find(
    (node) => node.class_type === "KSampler" || node.class_type === "KSamplerAdvanced",
  );
  const negativeRef = samplerNode?.inputs?.negative;
  if (!Array.isArray(negativeRef) || typeof negativeRef[0] !== "string") {
    return false;
  }

  const negativeNode = workflow[negativeRef[0]];
  return negativeNode?.class_type === "CLIPTextEncode"
    && typeof negativeNode.inputs?.text === "string";
}

export function prepareT2IWorkflow(
  baseWorkflow: Workflow,
  options: PrepareT2IWorkflowOptions,
): Workflow {
  const workflow = JSON.parse(JSON.stringify(baseWorkflow)) as Workflow;

  if (workflow["69"]?.inputs?.prompt !== undefined) {
    workflow["69"].inputs.prompt = options.prompt;
  } else if (workflow["64:27"] && typeof workflow["64:27"].inputs?.text === "string") {
    workflow["64:27"].inputs.text = options.prompt;
  } else {
    const promptNode = Object.values(workflow).find(
      (node) => node.class_type === "CLIPTextEncode" && typeof node.inputs?.text === "string",
    );
    if (promptNode?.inputs) {
      promptNode.inputs.text = options.prompt;
    }
  }

  const latentNode = workflow["64:13"]
    || workflow["57:13"]
    || Object.values(workflow).find((node) => node.class_type?.includes("LatentImage"));

  if (latentNode?.inputs) {
    latentNode.inputs.width = options.aspectRatio.w;
    latentNode.inputs.height = options.aspectRatio.h;
    latentNode.inputs.batch_size = options.batchSize;
  }

  if (workflowSupportsNegativePrompt(workflow)) {
    const samplerNode = Object.values(workflow).find(
      (node) => node.class_type === "KSampler" || node.class_type === "KSamplerAdvanced",
    );
    const negativeRef = samplerNode?.inputs?.negative as [string, number] | undefined;
    if (negativeRef) {
      workflow[negativeRef[0]].inputs!.text = options.negative;
    }
  }

  const samplerNode = Object.values(workflow).find(
    (node) => node.class_type === "KSampler" || node.class_type === "KSamplerAdvanced",
  );
  if (samplerNode?.inputs) {
    samplerNode.inputs.seed = options.seed;
  }

  return workflow;
}

export function extractImageUrlsFromExecutedMessage(
  message: ExecutedMessage,
  serverUrl: string,
): string[] {
  if (message.type !== "executed") {
    return [];
  }

  return toImageUrls(message.data?.output?.images, serverUrl);
}

export function extractImageUrlsFromHistory(
  history: Record<string, any>,
  promptId: string,
  serverUrl: string,
): string[] {
  const outputs = history[promptId]?.outputs;
  if (!outputs || typeof outputs !== "object") {
    return [];
  }

  for (const output of Object.values(outputs) as Array<{ images?: ComfyImage[] }>) {
    const urls = toImageUrls(output?.images, serverUrl);
    if (urls.length > 0) {
      return urls;
    }
  }

  return [];
}

function toImageUrls(images: ComfyImage[] | undefined, serverUrl: string): string[] {
  if (!Array.isArray(images) || images.length === 0) {
    return [];
  }

  return images.map((image) => {
    const params = new URLSearchParams({
      filename: image.filename,
      subfolder: image.subfolder ?? "",
      type: image.type ?? "output",
    });
    return `${serverUrl}/view?${params.toString()}`;
  });
}
