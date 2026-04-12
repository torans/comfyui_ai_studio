import { invoke } from "@tauri-apps/api/core";

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

interface LoginResponse {
  token: string;
  user: User;
}

export interface ParameterSchema {
  node: string;
  field: string;
  input_key?: string;
  label: string;
  type: string;
  default?: unknown;
  required?: boolean;
  options?: Record<string, string>;
  accept?: string;
  placeholder?: string;
}

export interface WorkflowTemplate {
  id: number;
  name: string;
  thumb?: string | null;
  code: string;
  type: string;
  version: string;
  is_active: boolean;
  category: string;
  category_label: string;
  description?: string | null;
  parameter_schema: ParameterSchema[];
}

export interface GenerationJob {
  id: number;
  type: string;
  status: string;
  workflow_template_id: number;
  workflow_code?: string;
  workflow_name?: string;
  input_json?: Record<string, unknown>;
  error_message?: string;
  started_at?: string;
  finished_at?: string;
  created_at: string;
  updated_at: string;
  progress?: number;
  message?: string;
  assets?: Array<{
    id: number;
    type: string;
    media_kind?: "image" | "video";
    filename: string;
    url: string;
  }>;
}

type GenerationJobAsset = NonNullable<GenerationJob["assets"]>[number];

type GenerationJobDetailResponse = {
  job_id: number;
  workflow_id?: number;
  workflow_name?: string;
  workflow_code?: string;
  category?: string;
  status: string;
  inputs?: Record<string, unknown>;
  error_message?: string;
  created_at: string;
  started_at?: string;
  finished_at?: string;
  progress?: number;
  message?: string;
  assets?: GenerationJobAsset[];
};

function normalizeGenerationJobDetail(job: GenerationJob | GenerationJobDetailResponse): GenerationJob {
  if ("id" in job) {
    return job;
  }

  return {
    id: job.job_id,
    type: job.category ?? "",
    status: job.status,
    workflow_template_id: job.workflow_id ?? 0,
    workflow_code: job.workflow_code,
    workflow_name: job.workflow_name,
    input_json: job.inputs,
    error_message: job.error_message,
    started_at: job.started_at,
    finished_at: job.finished_at,
    created_at: job.created_at,
    updated_at: job.finished_at ?? job.started_at ?? job.created_at,
    progress: job.progress,
    message: job.message,
    assets: job.assets,
  };
}

// 认证 API - 通过 Tauri 命令
export const auth = {
  async login(adminUrl: string, email: string, password: string): Promise<LoginResponse> {
    const result = await invoke<{ token: string; user: User }>('api_login', {
      adminUrl,
      email,
      password,
    });
    return result;
  },
};

// 工作流模板 API
export const workflowTemplates = {
  async list(adminUrl: string, token: string): Promise<{ data: WorkflowTemplate[] }> {
    const result = await invoke<{ data: WorkflowTemplate[] }>('api_get_workflows', {
      adminUrl,
      token,
    });
    return result;
  },
};

// 生成任务 API
export const generationJobs = {
  async create(
    adminUrl: string,
    token: string,
    workflowId: number,
    inputs: Record<string, unknown>,
    clientRequestId?: string
  ): Promise<{ job_id: number; status: string }> {
    const result = await invoke<{ job_id: number; status: string }>('api_create_job', {
      adminUrl,
      token,
      workflowId,
      inputs,
      clientRequestId,
    });
    return result;
  },

  async list(adminUrl: string, token: string, page: number = 1): Promise<{ data: GenerationJob[], meta: any }> {
    const result = await invoke<{ data: GenerationJob[], meta: any }>('api_get_jobs', {
      adminUrl,
      token,
      page,
    });
    return result;
  },

  async get(adminUrl: string, token: string, jobId: number): Promise<GenerationJob> {
    const result = await invoke<GenerationJob | GenerationJobDetailResponse>('api_get_job', {
      adminUrl,
      token,
      jobId,
    });
    return normalizeGenerationJobDetail(result);
  },
};

// ComfyUI 代理 API - 通过 Admin 代理访问 ComfyUI
export const comfyUiProxy = {
  async systemStats(adminUrl: string, token: string): Promise<{ online: boolean; data?: unknown; error?: string }> {
    const result = await invoke<{ online: boolean; data?: unknown; error?: string }>('api_comfyui_system_stats', {
      adminUrl,
      token,
    });
    return result;
  },

  async models(adminUrl: string, token: string): Promise<{ models: string[]; error?: string }> {
    const result = await invoke<{ models: string[]; error?: string }>('api_comfyui_models', {
      adminUrl,
      token,
    });
    return result;
  },

  async uploadImage(adminUrl: string, token: string, path: string): Promise<{ name: string; filename: string; error?: string }> {
    const result = await invoke<{ name: string; filename: string; error?: string }>('api_upload_image_to_comfyui', {
      adminUrl,
      token,
      path,
    });
    return result;
  },

  async uploadToAdmin(adminUrl: string, token: string, fileData: number[], fileName: string, mimeType: string): Promise<{ id: number; path: string; url: string }> {
    const result = await invoke<{ id: number; path: string; url: string }>('api_upload_to_admin', {
      adminUrl,
      token,
      fileData,
      fileName,
      mimeType,
    });
    return result;
  },

  async uploadWorkflowImage(adminUrl: string, token: string, fileData: number[], fileName: string, mimeType: string): Promise<{
    id: number;
    path: string;
    url: string;
    input_value: string;
    comfyui: {
      name: string;
      subfolder: string;
      type: string;
    };
  }> {
    const result = await invoke<{
      id: number;
      path: string;
      url: string;
      input_value: string;
      comfyui: {
        name: string;
        subfolder: string;
        type: string;
      };
    }>('api_upload_workflow_image', {
      adminUrl,
      token,
      fileData,
      fileName,
      mimeType,
    });
    return result;
  },

  async downloadRemoteMedia(url: string, suggestedFilename?: string): Promise<{ saved_path: string }> {
    const result = await invoke<{ saved_path: string }>("api_download_remote_media", {
      url,
      suggestedFilename,
    });
    return result;
  },
};
