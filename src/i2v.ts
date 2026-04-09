export interface VideoWorkflowNode {
  class_type?: string;
  inputs?: Record<string, any>;
}

export type VideoWorkflow = Record<string, VideoWorkflowNode>;

interface RatioConfig {
  w: number;
  h: number;
}

interface PrepareI2VWorkflowOptions {
  uploadedImageName: string;
  prompt: string;
  aspectRatio: RatioConfig;
  seed: number;
}

interface OutputFile {
  filename: string;
  subfolder?: string;
  type?: string;
}

export function prepareI2VWorkflow(
  baseWorkflow: VideoWorkflow,
  options: PrepareI2VWorkflowOptions,
): VideoWorkflow {
  const workflow = JSON.parse(JSON.stringify(baseWorkflow)) as VideoWorkflow;

  if (workflow["269"]?.inputs) {
    workflow["269"].inputs.image = options.uploadedImageName;
  }

  if (workflow["267:266"]?.inputs) {
    workflow["267:266"].inputs.value = options.prompt;
  }

  if (workflow["267:257"]?.inputs) {
    workflow["267:257"].inputs.value = options.aspectRatio.w;
  }
  if (workflow["267:258"]?.inputs) {
    workflow["267:258"].inputs.value = options.aspectRatio.h;
  }

  for (const node of Object.values(workflow)) {
    if (node.class_type === "RandomNoise" && node.inputs) {
      node.inputs.noise_seed = options.seed;
    }
  }

  return workflow;
}

export function extractVideoUrlsFromHistory(
  history: Record<string, any>,
  promptId: string,
  serverUrl: string,
): string[] {
  const outputs = history[promptId]?.outputs;
  if (!outputs || typeof outputs !== "object") {
    return [];
  }

  for (const output of Object.values(outputs) as Array<Record<string, any>>) {
    const urls = extractOutputFileUrls(output, serverUrl);
    if (urls.length > 0) {
      return urls;
    }
  }

  return [];
}

function extractOutputFileUrls(output: Record<string, any>, serverUrl: string): string[] {
  for (const value of Object.values(output)) {
    if (Array.isArray(value)) {
      const files = value.filter(isOutputFile);
      if (files.length > 0) {
        return files.map((file) => toViewUrl(file, serverUrl));
      }
    }
  }

  return [];
}

function isOutputFile(value: unknown): value is OutputFile {
  return Boolean(
    value
      && typeof value === "object"
      && "filename" in value
      && typeof (value as OutputFile).filename === "string",
  );
}

function toViewUrl(file: OutputFile, serverUrl: string): string {
  const params = new URLSearchParams({
    filename: file.filename,
    subfolder: file.subfolder ?? "",
    type: file.type ?? "output",
  });
  return `${serverUrl}/view?${params.toString()}`;
}
