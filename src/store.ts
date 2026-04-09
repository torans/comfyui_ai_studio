import { create } from 'zustand';

type ThemeMode = 'light' | 'dark' | 'auto';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
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
  created_at?: string;
  updated_at?: string;
  progress?: number;
  message?: string;
  assets?: Array<{
    id: number;
    type: string;
    filename: string;
    url: string;
  }>;
}

interface ParameterSchema {
  node: string;
  field: string;
  label: string;
  type: string;
  default?: unknown;
  required?: boolean;
  options?: Record<string, string>;
}

interface WorkflowTemplate {
  id: number;
  name: string;
  code: string;
  type: string;
  category: string;
  category_label: string;
  description?: string;
  parameter_schema: ParameterSchema[];
}

interface AppState {
  // Auth state
  token: string | null;
  user: User | null;
  isAuthenticated: boolean;

  // Theme state
  theme: ThemeMode;

  // ComfyUI state
  serverUrl: string;
  isConnected: boolean;
  checkpoints: string[];
  selectedModel: string;

  // Workflow state
  workflows: WorkflowTemplate[];
  selectedWorkflowId: number | null;

  // Job state
  jobs: GenerationJob[];
  currentJobId: number | null;

  // Auth actions
  setToken: (token: string | null) => void;
  setUser: (user: User | null) => void;
  login: (token: string, user: User) => void;
  logout: () => void;

  // Theme actions
  setTheme: (theme: ThemeMode) => void;

  // ComfyUI actions
  setServerUrl: (url: string) => void;
  setConnected: (status: boolean) => void;
  setCheckpoints: (list: string[]) => void;
  setSelectedModel: (model: string) => void;

  // Workflow actions
  setWorkflows: (workflows: WorkflowTemplate[]) => void;
  setSelectedWorkflowId: (id: number | null) => void;

  // Job actions
  setJobs: (jobs: GenerationJob[]) => void;
  setCurrentJobId: (jobId: number | null) => void;
  updateJob: (jobId: number, updates: Partial<GenerationJob>) => void;
}

// 从 localStorage 恢复状态
const storedToken = localStorage.getItem('auth_token');
const storedUser = localStorage.getItem('auth_user');
const storedTheme = localStorage.getItem('app_theme') as ThemeMode || 'auto';

export const useStore = create<AppState>((set) => ({
  // Auth state
  token: storedToken,
  user: storedUser ? JSON.parse(storedUser) : null,
  isAuthenticated: !!storedToken,

  // Theme state
  theme: storedTheme,

  // ComfyUI state
  serverUrl: 'http://admin.test',
  isConnected: false,
  checkpoints: [],
  selectedModel: '',

  // Workflow state
  workflows: [],
  selectedWorkflowId: null,

  // Job state
  jobs: [],
  currentJobId: null,

  // Auth actions
  setToken: (token: string | null) => {
    if (token) {
      localStorage.setItem('auth_token', token);
    } else {
      localStorage.removeItem('auth_token');
    }
    set({ token });
  },

  setUser: (user: User | null) => {
    if (user) {
      localStorage.setItem('auth_user', JSON.stringify(user));
    } else {
      localStorage.removeItem('auth_user');
    }
    set({ user });
  },

  login: (token: string, user: User) => {
    localStorage.setItem('auth_token', token);
    localStorage.setItem('auth_user', JSON.stringify(user));
    set({ token, user, isAuthenticated: true });
  },

  logout: () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
    set({ token: null, user: null, isAuthenticated: false, jobs: [], currentJobId: null });
  },

  // Theme actions
  setTheme: (theme: ThemeMode) => {
    localStorage.setItem('app_theme', theme);
    set({ theme });
  },

  // ComfyUI actions
  setServerUrl: (url: string) => set({ serverUrl: url }),
  setConnected: (status: boolean) => set({ isConnected: status }),
  setCheckpoints: (list: string[]) => set({ checkpoints: list }),
  setSelectedModel: (model: string) => set({ selectedModel: model }),

  // Workflow actions
  setWorkflows: (workflows: WorkflowTemplate[]) => set({ workflows }),
  setSelectedWorkflowId: (id: number | null) => set({ selectedWorkflowId: id }),

  // Job actions
  setJobs: (jobs: GenerationJob[]) => set({ jobs }),
  setCurrentJobId: (jobId: number | null) => set({ currentJobId: jobId }),
  updateJob: (jobId: number, updates: Partial<GenerationJob>) => set((state) => {
    const exists = state.jobs.some((job) => job.id === jobId);
    if (!exists) {
      return { jobs: [{ id: jobId, ...updates } as GenerationJob, ...state.jobs] };
    }
    return {
      jobs: state.jobs.map((job) =>
        job.id === jobId ? { ...job, ...updates } : job
      ),
    };
  }),
}));
