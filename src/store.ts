import { create } from 'zustand';
import { GenerationJob, WorkflowTemplate, ParameterSchema } from './api';

type ThemeMode = 'light' | 'dark' | 'auto';

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
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

const storage = typeof window !== 'undefined' ? window.localStorage : null;

// 从 localStorage 恢复状态
const storedToken = storage?.getItem('auth_token') ?? null;
const storedUser = storage?.getItem('auth_user') ?? null;
const storedTheme = (storage?.getItem('app_theme') as ThemeMode | null) ?? 'auto';
const storedServerUrl = storage?.getItem('server_url') ?? 'http://admin.test';

export const useStore = create<AppState>((set) => ({
  // Auth state
  token: storedToken,
  user: storedUser ? JSON.parse(storedUser) : null,
  isAuthenticated: !!storedToken,

  // Theme state
  theme: storedTheme,

  // ComfyUI state
  serverUrl: storedServerUrl,
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
      storage?.setItem('auth_token', token);
    } else {
      storage?.removeItem('auth_token');
    }
    set({ token });
  },

  setUser: (user: User | null) => {
    if (user) {
      storage?.setItem('auth_user', JSON.stringify(user));
    } else {
      storage?.removeItem('auth_user');
    }
    set({ user });
  },

  login: (token: string, user: User) => {
    storage?.setItem('auth_token', token);
    storage?.setItem('auth_user', JSON.stringify(user));
    set({ token, user, isAuthenticated: true });
  },

  logout: () => {
    storage?.removeItem('auth_token');
    storage?.removeItem('auth_user');
    set({ token: null, user: null, isAuthenticated: false, jobs: [], currentJobId: null });
  },

  // Theme actions
  setTheme: (theme: ThemeMode) => {
    storage?.setItem('app_theme', theme);
    set({ theme });
  },

  // ComfyUI actions
  setServerUrl: (url: string) => {
    storage?.setItem('server_url', url);
    set({ serverUrl: url });
  },
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
