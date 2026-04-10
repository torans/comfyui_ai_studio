import { useState, useEffect, useCallback, useRef, useId, type DragEvent } from "react";
import {
  Image as ImageIcon,
  Zap,
  Play,
  Loader2,
  Menu,
  Download,
  ZoomIn,
  ZoomOut,
  Maximize2,
  Maximize,
  Sparkles,
  LogOut,
  User,
  Clock,
  Settings2,
  Video,
  ChevronRight,
  ChevronLeft,
  Eye,
  EyeOff,
  X,
} from "lucide-react";
import { getCurrentWindow } from "@tauri-apps/api/window";
import { useStore } from "./store";
import { auth, generationJobs, workflowTemplates, comfyUiProxy, WorkflowTemplate, GenerationJob } from "./api";
import { listenToJobStatus } from "./echo";
import { useTheme } from "./hooks/useTheme";
import { useVideoPoster } from "./hooks/useVideoPoster";
import VideoPlayer from "./components/VideoPlayer";
import "./App.css";

type Tab = "Workflow" | "Jobs" | "Settings";

const isUploadField = (type?: string) => type === "image" || type === "upload";

type MediaAsset = {
  id?: number;
  type?: string;
  media_kind?: "image" | "video";
  filename?: string;
  url: string;
};

const getMediaKind = (asset: MediaAsset): "image" | "video" => {
  if (asset.media_kind === "video" || asset.media_kind === "image") {
    return asset.media_kind;
  }

  const filename = asset.filename?.toLowerCase() || "";
  if (filename.endsWith(".mp4") || filename.endsWith(".webm") || filename.endsWith(".mov") || filename.endsWith(".mkv")) {
    return "video";
  }

  const type = asset.type?.toLowerCase() || "";
  if (type.includes("video")) {
    return "video";
  }

  const url = asset.url.toLowerCase();
  if (url.includes(".mp4") || url.includes(".webm") || url.includes(".mov") || url.includes(".mkv")) {
    return "video";
  }

  return "image";
};

const getSuggestedFilename = (asset: Pick<MediaAsset, "url" | "filename">): string | undefined => {
  if (asset.filename && asset.filename.trim()) {
    return asset.filename.trim();
  }

  try {
    const pathname = new URL(asset.url).pathname;
    const lastSegment = pathname.split("/").filter(Boolean).pop();
    return lastSegment ? decodeURIComponent(lastSegment) : undefined;
  } catch {
    return undefined;
  }
};

const saveRemoteMedia = async (asset: Pick<MediaAsset, "url" | "filename">): Promise<string> => {
  const result = await comfyUiProxy.downloadRemoteMedia(asset.url, getSuggestedFilename(asset));
  return result.saved_path;
};

const isVideoWorkflowType = (value?: string): boolean => {
  const normalized = (value || "").toLowerCase();
  return normalized === "i2v"
    || normalized === "t2v"
    || normalized.includes("video");
};

const resolveMediaKind = (asset: MediaAsset, workflowType?: string): "image" | "video" => {
  const detected = getMediaKind(asset);
  if (detected === "video") {
    return detected;
  }

  if (isVideoWorkflowType(workflowType)) {
    return "video";
  }

  return detected;
};

const getSchemaInputKey = (schemaItem: any, index: number): string => {
  if (typeof schemaItem?.input_key === "string" && schemaItem.input_key.trim()) {
    return schemaItem.input_key.trim();
  }

  const field =
    typeof schemaItem?.field === "string" && schemaItem.field.trim()
      ? schemaItem.field.trim()
      : "param";
  const node =
    typeof schemaItem?.node === "string" && schemaItem.node.trim()
      ? schemaItem.node.trim()
      : typeof schemaItem?.node === "number"
        ? String(schemaItem.node)
        : "";

  return node ? `${field}__${node}` : `${field}__${index}`;
};

const normalizeSchemaValue = (value: unknown): any[] => {
  if (!value) return [];

  if (typeof value === "string" && value.trim()) {
    try {
      return normalizeSchemaValue(JSON.parse(value));
    } catch {
      return [];
    }
  }

  if (Array.isArray(value)) return value;

  if (typeof value === "object") {
    const record = value as Record<string, unknown>;

    if (Array.isArray(record.nodes)) return record.nodes as any[];
    if (Array.isArray(record.schema)) return record.schema as any[];
    if (Array.isArray(record.inputs)) return record.inputs as any[];
    if (Array.isArray(record.data)) return record.data as any[];

    return Object.entries(record)
      .filter(([, item]) => typeof item === "object" && item !== null)
      .map(([key, item]) => {
        const config = item as Record<string, unknown>;
        return {
          ...config,
          input_key:
            typeof config.input_key === "string" && config.input_key.trim()
              ? config.input_key
              : key,
        };
      });
  }

  return [];
};

// ── App Container ─────────────────────────────────────────────────────────────
export default function App() {
  useTheme();
  const store = useStore();
  const [activeTab, setActiveTab] = useState<Tab>("Workflow");
  const [selectedCategory, setSelectedCategory] = useState<string>("");
  const [isConnected, setConnected] = useState(false);

  // 初始化获取工作流
  useEffect(() => {
    if (store.isAuthenticated && store.token) {
      const initData = async () => {
        try {
          const result = await workflowTemplates.list(store.token!);
          store.setWorkflows(result.data);
          
          if (result.data.length > 0) {
            const firstCat = result.data[0].category;
            setSelectedCategory(firstCat);
            if (!store.selectedWorkflowId) store.setSelectedWorkflowId(result.data[0].id);
          }

          if (store.user?.id) {
            listenToJobStatus(store.token!, store.user.id, "http://admin.test", (data) => {
              store.updateJob(data.id, data);
            });
          }
        } catch (err) { console.error("初始化失败:", err); }
      };
      initData();
    }
  }, [store.isAuthenticated, store.token, store.user?.id]);

  useEffect(() => {
     if (!store.isAuthenticated || !store.token) return;
     const check = async () => {
        try {
          const res = await comfyUiProxy.systemStats(store.token!);
          setConnected(res.online);
        } catch { setConnected(false); }
     };
     check();
     const timer = setInterval(check, 10000);
     return () => clearInterval(timer);
  }, [store.token]);

  if (!store.isAuthenticated) return <div className="app-container"><LoginView /></div>;

  const workflowsByCategory = store.workflows.reduce((acc, w) => {
    const cat = w.category || 'other';
    const label = w.category_label || '其他';
    if (!acc[cat]) acc[cat] = { label, items: [] };
    acc[cat].items.push(w);
    return acc;
  }, {} as Record<string, { label: string, items: WorkflowTemplate[] }>);

  return (
    <div className="app-container">
      <div className="custom-titlebar" onMouseDown={(e) => { if (e.buttons === 1) getCurrentWindow().startDragging(); }}>
        <div className="titlebar-title">Beikuman AI Studio</div>
      </div>
      
      <div className="app-body">
        {/* 1. 一级主侧边栏 (分类列表) */}
        <aside className="main-sidebar">
          <div className="sidebar-logo"><Sparkles size={24} /></div>
          
          <nav className="category-nav">
            {Object.entries(workflowsByCategory).map(([id, category]) => (
              <button 
                key={id}
                className={`cat-item ${selectedCategory === id && activeTab === "Workflow" ? "active" : ""}`}
                onClick={() => { setSelectedCategory(id); setActiveTab("Workflow"); }}
              >
                {id.includes('video') ? <Video size={20} /> : id.includes('i2i') ? <ImageIcon size={20} /> : <Zap size={20} />}
                <span>{category.label}</span>
              </button>
            ))}
            
            <div className="nav-divider" />
            
            <button className={`cat-item ${activeTab === "Jobs" ? "active" : ""}`} onClick={() => setActiveTab("Jobs")}>
              <Clock size={20} /><span>任务记录</span>
            </button>
          </nav>

          <div className="sidebar-footer">
            <button className={`cat-item ${activeTab === "Settings" ? "active" : ""}`} onClick={() => setActiveTab("Settings")}>
              <Settings2 size={20} />
            </button>
            <div className={`status-indicator ${isConnected ? "online" : ""}`} />
          </div>
        </aside>

        {/* 2. 次级侧边栏 (当前分类下的所有工作流) */}
        {activeTab === "Workflow" && (
          <aside className="sub-sidebar">
            <div className="sub-header">
              <div className="sub-header-copy">
                <h3>{workflowsByCategory[selectedCategory]?.label || "选择分类"}</h3>
                <span className="sub-header-meta">
                  共 {workflowsByCategory[selectedCategory]?.items.length || 0} 个小应用
                </span>
              </div>
            </div>
            <div className="workflow-list">
              {workflowsByCategory[selectedCategory]?.items.map(w => (
                <button 
                  key={w.id} 
                  className={`workflow-item ${store.selectedWorkflowId === w.id ? 'active' : ''}`}
                  onClick={() => store.setSelectedWorkflowId(w.id)}
                >
                  <div className="w-label-group">
                    <span className="w-name">{w.name}</span>
                    <span className="w-ver">{w.version}</span>
                  </div>
                  <ChevronRight size={14} className="w-arrow" />
                </button>
              ))}
            </div>
          </aside>
        )}

        {/* 3. 主内容区 */}
        <main className="main-content">
          {activeTab === "Workflow" ? <DynamicWorkflowView /> : 
           activeTab === "Jobs" ? <JobsView /> : <SettingsView />}
        </main>
      </div>
    </div>
  );
}

// ── Dynamic Workflow View ───────────────────────────────────────────────────
const DynamicWorkflowView = () => {
  const { token, workflows, selectedWorkflowId } = useStore();
  const currentWorkflow = workflows.find(w => w.id === selectedWorkflowId);
  
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [generating, setGenerating] = useState(false);
  const [progress, setProgress] = useState(0);
  const [resultMedia, setResultMedia] = useState<MediaAsset[]>([]);
  const [selectedMediaIdx, setSelectedMediaIdx] = useState(0);
  const [currentJobId, setCurrentJobId] = useState<number | null>(null);
  const currentJob = useStore(state => state.jobs.find(j => j.id === currentJobId));
  const progressMessage = currentJob?.message || "等待后端返回实时进度";
  const [toastState, setToastState] = useState<{ title: string; message: string } | null>(null);

  // 调试输出：看看后端到底给的是什么
  useEffect(() => {
    if (currentWorkflow) {
      console.log("当前工作流完整数据:", currentWorkflow);
    }
  }, [selectedWorkflowId]);

  const getSchema = () => {
    if (!currentWorkflow) return [];
    const possibleFields = ['parameter_schema', 'parameter_schema_json', 'schema', 'inputs', 'data'];
    for (const field of possibleFields) {
      if ((currentWorkflow as any)[field]) {
        const normalized = normalizeSchemaValue((currentWorkflow as any)[field]);
        if (normalized.length > 0) return normalized;
      }
    }
    return [];
  };

  const schema = getSchema();

  useEffect(() => {
    if (schema && schema.length > 0) {
      const defaults: Record<string, any> = {};
      schema.forEach((p: any, index: number) => {
        const inputKey = getSchemaInputKey(p, index);
        defaults[inputKey] = formData[inputKey] ?? p.default ?? (p.type === 'number' ? 0 : "");
      });
      setFormData(defaults);
    }
  }, [selectedWorkflowId, JSON.stringify(schema)]);

  useEffect(() => {
    if (!currentJob) return;
    if (currentJob.status === 'succeeded') {
      setGenerating(false); setProgress(100);
      if (currentJob.assets) {
        setResultMedia(currentJob.assets.filter(a => a.type === 'output'));
      }
    } else if (currentJob.status === 'failed') {
      setGenerating(false);
      setProgress(0);
      setToastState({
        title: '生成失败',
        message: currentJob.error_message || '任务生成失败，请调整参数后重试。',
      });
    } else {
      // 关键修复：只有当后端进度确实增长了，才更新本地状态，防止被旧信号强制拉回
      const newProg = currentJob.progress || 0;
      if (newProg > progress) setProgress(newProg);
    }
  }, [currentJob?.status, currentJob?.progress, currentJob?.assets, progress]);

  useEffect(() => {
    if (!toastState) return;
    const timer = window.setTimeout(() => setToastState(null), 3200);
    return () => window.clearTimeout(timer);
  }, [toastState]);

  const handleGenerate = async () => {
    if (generating || !token || !currentWorkflow) return;
    setCurrentJobId(null);
    setGenerating(true); setProgress(0); setResultMedia([]); setSelectedMediaIdx(0);
    try {
      const finalInputs: Record<string, unknown> = {};
      for (const [index, p] of schema.entries()) {
        const inputKey = getSchemaInputKey(p, index);
        const currentValue = formData[inputKey] ?? p.default ?? null;

        if (p.required && (currentValue === null || currentValue === undefined || currentValue === "")) {
          setToastState({
            title: '必填项未完成',
            message: `${p.label || inputKey} 为必填项，请补充后再开始生成。`,
          });
          setGenerating(false);
          return;
        }

        if (isUploadField(p.type) && currentValue instanceof File) {
          const file = currentValue;
          const arrayBuffer = await file.arrayBuffer();
          const uploadRes = await comfyUiProxy.uploadWorkflowImage(
            token,
            Array.from(new Uint8Array(arrayBuffer)),
            file.name,
            file.type || "application/octet-stream"
          );
          finalInputs[inputKey] = uploadRes.input_value;
          continue;
        }

        if (currentValue !== null) {
          finalInputs[inputKey] = currentValue;
        }
      }
      const job = await generationJobs.create(token, currentWorkflow.id, finalInputs);
      setCurrentJobId(job.job_id);
      setToastState({
        title: "已加入生成队列",
        message: "任务已开始生成，结果稍后会出现在任务记录里。",
      });
    } catch (err: any) {
      setToastState({
        title: "提交失败",
        message: err?.message || "任务提交失败，请稍后重试。",
      });
      setGenerating(false);
    }
  };

  if (!currentWorkflow) return <div className="full-loading"><Loader2 className="spinning" /></div>;

  return (
    <div className="dynamic-view">
      {toastState ? (
        <div className="app-toast" role="status" aria-live="polite">
          <span className="app-toast-title">{toastState.title}</span>
          <span className="app-toast-message">{toastState.message}</span>
        </div>
      ) : null}
      <section className="params-area">
        <header>
          <h2>{currentWorkflow.name}</h2>
        </header>

        <div className="fields-scroller">
          {schema.length === 0 && <p className="no-params-hint">未检测到可配置参数</p>}
          {schema.map((p: any, index: number) => {
            const inputKey = getSchemaInputKey(p, index);

            return (
            <div key={inputKey} className="form-group">
              <label>{p.label}</label>
              {p.type === 'textarea' || (p.type === 'string' && p.field.includes('prompt')) ? (
                <textarea rows={3} value={formData[inputKey] || ""} onChange={e => setFormData({...formData, [inputKey]: e.target.value})} />
              ) : p.type === 'select' ? (
                <select
                  aria-label={p.label}
                  value={formData[inputKey] || p.default || ""}
                  onChange={e => setFormData({...formData, [inputKey]: e.target.value})}
                >
                  {Object.entries(p.options || {}).map(([value, label]) => (
                    <option key={value} value={value}>{String(label)}</option>
                  ))}
                </select>
              ) : p.type === 'number' || p.type === 'integer' ? (
                 <div className="slider-box">
                    <input type="range" min={p.field === 'seed' ? 0 : (p.min ?? 1)} max={p.field === 'seed' ? 1e16 : (p.max ?? 1024)} step={p.step ?? 1} value={formData[inputKey] || 0} onChange={e => setFormData({...formData, [inputKey]: Number(e.target.value)})} />
                    <input type="number" className="small-num" value={formData[inputKey] || 0} onChange={e => setFormData({...formData, [inputKey]: Number(e.target.value)})} />
                 </div>
              ) : isUploadField(p.type) ? (
                <ImageUploadField
                  accept={p.accept}
                  field={p.field}
                  label={p.label}
                  onChange={(file) => setFormData((prev) => ({ ...prev, [inputKey]: file }))}
                  placeholder={p.placeholder}
                  value={formData[inputKey]}
                />
              ) : (
                <input type="text" value={formData[inputKey] || ""} onChange={e => setFormData({...formData, [inputKey]: e.target.value})} />
              )}
            </div>
          )})}
        </div>

        <div className="action-bar">
          <button className="main-exec-btn" disabled={generating} onClick={handleGenerate}>
            {generating ? <Loader2 className="spinning" /> : <Play fill="currentColor" size={16} />}
            <span>{generating ? `生成中 (${progress}%)` : "开始生成"}</span>
          </button>
        </div>
      </section>

      <section className="display-area">
        {resultMedia.length > 0 ? (
           <div className="image-gallery">
              <div className="gallery-main">
                {resolveMediaKind(resultMedia[selectedMediaIdx], currentWorkflow?.type ?? currentWorkflow?.category) === "video" ? (
                  <VideoViewer
                    src={resultMedia[selectedMediaIdx].url}
                    filename={resultMedia[selectedMediaIdx].filename}
                    onToast={(title, message) => setToastState({ title, message })}
                  />
                ) : (
                  <ImageViewer
                    src={resultMedia[selectedMediaIdx].url}
                    filename={resultMedia[selectedMediaIdx].filename}
                    onToast={(title, message) => setToastState({ title, message })}
                  />
                )}
              </div>
              <div className="gallery-thumbs">
                {resultMedia.map((asset, idx) => (
                  <button
                    key={asset.id ?? `${asset.url}-${idx}`}
                    className={`thumb-item ${selectedMediaIdx === idx ? 'active' : ''}`}
                    onClick={() => setSelectedMediaIdx(idx)}
                  >
                    {resolveMediaKind(asset, currentWorkflow?.type ?? currentWorkflow?.category) === "video" ? (
                      <VideoPosterThumb src={asset.url} alt={`视频 ${idx + 1}`} badge />
                    ) : (
                      <img src={asset.url} alt={`图 ${idx + 1}`} />
                    )}
                  </button>
                ))}
              </div>
           </div>
        ) : generating ? (
           <div className="full-center-state">
             <div className="pulse-orb">
               <Sparkles size={64} className="spinning-slow" />
               <div className="prog-val">{progress}%</div>
             </div>
             <h3>画师正在潜心创作...</h3>
             <p>{progressMessage}</p>
           </div>
        ) : (
           <div className="full-center-state">
              <Zap size={64} className="faint-icon" />
              <h3>工作流就绪</h3>
              {schema.length > 0 ? <p>请在左侧配置参数并点击开始生成</p> : <p>此工作流属于全自动化流程，可直接点击开始生成</p>}
           </div>
        )}
      </section>
    </div>
  );
};

type ImageUploadFieldProps = {
  accept?: string;
  field: string;
  label: string;
  onChange: (file: File | null) => void;
  placeholder?: string;
  value?: File | null;
};

const ImageUploadField = ({ accept, field, label, onChange, placeholder, value }: ImageUploadFieldProps) => {
  const [isDragging, setIsDragging] = useState(false);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const inputId = useId();

  useEffect(() => {
    if (!(value instanceof File)) {
      setPreviewUrl(null);
      return;
    }

    const objectUrl = URL.createObjectURL(value);
    setPreviewUrl(objectUrl);

    return () => URL.revokeObjectURL(objectUrl);
  }, [value]);

  const handleFiles = (files: FileList | null) => {
    const file = files?.[0] ?? null;
    onChange(file);
  };

  const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
    event.preventDefault();
    setIsDragging(false);
    handleFiles(event.dataTransfer.files);
  };

  return (
    <div
      className={`upload-zone ${isDragging ? "dragging" : ""} ${previewUrl ? "has-file" : ""}`}
      onClick={() => inputRef.current?.click()}
      onDragEnter={(event) => {
        event.preventDefault();
        setIsDragging(true);
      }}
      onDragOver={(event) => {
        event.preventDefault();
        setIsDragging(true);
      }}
      onDragLeave={(event) => {
        event.preventDefault();
        if (event.currentTarget === event.target) setIsDragging(false);
      }}
      onDrop={handleDrop as unknown as React.DragEventHandler<HTMLDivElement>}
    >
      <input
        id={inputId}
        accept={accept}
        aria-label={label}
        className="upload-input"
        ref={inputRef}
        type="file"
        onClick={(event) => event.stopPropagation()}
        onChange={(event) => handleFiles(event.target.files)}
      />

      {previewUrl ? (
        <div className="upload-preview">
          <img src={previewUrl} alt={value?.name || label} />
          <div className="upload-overlay">
            <span className="upload-file-name">{value?.name}</span>
            <span className="upload-file-action">点击或拖拽更换图片</span>
          </div>
        </div>
      ) : (
        <div className="upload-empty-state">
          <div className="upload-empty-icon">
            <ImageIcon size={26} />
          </div>
          <div className="upload-empty-copy">
            <strong>{placeholder || "点击上传或拖拽图片到这里"}</strong>
            <span>{accept ? `支持 ${accept}` : "支持常见图片格式"}</span>
          </div>
        </div>
      )}

      <div className="upload-chip-row">
        <span className="upload-chip">拖拽上传</span>
        <span className="upload-chip">本地图片</span>
      </div>
    </div>
  );
};

// ── Shared UI Components ─────────────────────────────────────────────────────
const LoginView = () => {
    const store = useStore();
    const [e, setE] = useState("");
    const [p, setP] = useState("");
    const [l, setL] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [loginError, setLoginError] = useState<string | null>(null);
    const canSubmit = e.trim().length > 0 && p.trim().length > 0 && !l;
    const handleLogin = async () => {
        if (!canSubmit) {
            setLoginError("请输入员工账号与访问密码后再继续。");
            return;
        }

        setL(true);
        setLoginError(null);
        try {
            const res = await auth.login(e.trim(), p);
            store.login(res.token, res.user);
        }
        catch (err: any) {
            setLoginError(err?.message || "认证失败，请检查账号或密码后重试。");
        }
        finally { setL(false); }
    };
    return (
        <div className="login-shell">
            <section className="login-hero-panel">
                <div className="login-hero-art">
                    <div className="hero-orb hero-orb-top" />
                    <div className="hero-orb hero-orb-bottom" />
                    <div className="hero-glass hero-glass-large" />
                    <div className="hero-glass hero-glass-mid" />
                    <div className="hero-glass hero-glass-small" />
                </div>
                <div className="login-hero-copy">
                    <span className="login-hero-badge">Beikuman AI Studio</span>
                    <h2>协同进化，智算未来</h2>
                    <p>打破创意边界，重塑生产效能。</p>
                </div>
            </section>

            <section className="login-form-panel">
                <div className="login-form-wrap">
                    <div className="login-brand-mark">
                        <span className="login-brand-icon"><Sparkles size={20} /></span>
                        <span className="login-brand-name">Beikuman AI Studio</span>
                    </div>

                    <div className="login-copy-block">
                        <h1>欢迎回来</h1>
                        <p>请输入员工账号与访问密码，继续使用工作台。</p>
                    </div>

                    <div className="login-fields">
                        <label className="login-field">
                            <span>员工账号</span>
                            <input
                                placeholder="请输入员工账号"
                                value={e}
                                onChange={i => {
                                    setE(i.target.value);
                                    if (loginError) setLoginError(null);
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === "Enter" && !l) void handleLogin();
                                }}
                                aria-invalid={!!loginError}
                            />
                        </label>

                        <label className="login-field">
                            <span>访问密码</span>
                            <div className="login-password-wrap">
                                <input
                                    type={showPassword ? "text" : "password"}
                                    placeholder="请输入访问密码"
                                    value={p}
                                    onChange={i => {
                                        setP(i.target.value);
                                        if (loginError) setLoginError(null);
                                    }}
                                    onKeyDown={(event) => {
                                        if (event.key === "Enter" && !l) void handleLogin();
                                    }}
                                    aria-invalid={!!loginError}
                                />
                                <button
                                    type="button"
                                    className="login-password-toggle"
                                    onClick={() => setShowPassword((prev) => !prev)}
                                    aria-label={showPassword ? "隐藏密码" : "显示密码"}
                                >
                                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                                </button>
                            </div>
                        </label>
                    </div>

                    <button type="button" className="login-submit-btn" onClick={handleLogin} disabled={!canSubmit}>
                        {l ? "正在认证..." : "登录工作台"}
                    </button>

                    {loginError ? <p className="login-error-banner" role="alert">{loginError}</p> : null}
                    <p className="login-footnote">内部系统仅限已授权员工访问</p>
                </div>
            </section>
        </div>
    );
};

const JobsView = () => {
    const { token, jobs, setJobs } = useStore();
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const [loading, setLoading] = useState(false);
    const [viewerJob, setViewerJob] = useState<GenerationJob | null>(null);
    const [toastState, setToastState] = useState<{ title: string; message: string } | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);

    const loadMore = useCallback(async (reset = false) => {
        if (!token || loading || (!hasMore && !reset)) return;
        setLoading(true);
        const nextPage = reset ? 1 : page;
        try {
            const res = await generationJobs.list(token, nextPage);
            const newList = res.data;
            if (reset) {
                setJobs(newList);
            } else {
                setJobs([...jobs, ...newList]);
            }
            setPage(nextPage + 1);
            setHasMore(res.meta.current_page < res.meta.last_page);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    }, [token, page, hasMore, loading, jobs, setJobs]);

    useEffect(() => { loadMore(true); }, [token]);

    const handleScroll = () => {
        if (!scrollRef.current) return;
        const { scrollTop, scrollHeight, clientHeight } = scrollRef.current;
        if (scrollHeight - scrollTop <= clientHeight + 100) {
            loadMore();
        }
    };

    const getOutputMedia = (j: GenerationJob): MediaAsset[] => {
        return j.assets?.filter(a => a.type === 'output') || [];
    };

    useEffect(() => {
        if (!toastState) return;
        const timer = window.setTimeout(() => setToastState(null), 3200);
        return () => window.clearTimeout(timer);
    }, [toastState]);

    return (
        <div className="jobs-panel">
            {toastState ? (
                <div className="app-toast" role="status" aria-live="polite">
                    <span className="app-toast-title">{toastState.title}</span>
                    <span className="app-toast-message">{toastState.message}</span>
                </div>
            ) : null}
            <header className="panel-header">
                <div>
                    <h1>任务记录</h1>
                    <p>查看历史生成记录与任务进度</p>
                </div>
                {loading && <Loader2 className="spinning text-accent" />}
            </header>
            
            <div className="jobs-scroller" ref={scrollRef} onScroll={handleScroll}>
                <div className="jobs-masonry">
                    {jobs.map(j => (
                        <div key={j.id} className={`job-card ${j.status}`} onClick={() => j.status === 'succeeded' && setViewerJob(j)}>
                            <div className="job-thumb">
                                {getOutputMedia(j)[0] ? (
                                    resolveMediaKind(getOutputMedia(j)[0], j.type) === "video" ? (
                                        <div className="job-video-thumb">
                                            <VideoPosterThumb
                                              src={getOutputMedia(j)[0].url}
                                              alt={j.workflow_name || "视频封面"}
                                              badge
                                            />
                                        </div>
                                    ) : (
                                        <img src={getOutputMedia(j)[0].url} alt={j.workflow_name} loading="lazy" />
                                    )
                                ) : (
                                    <div className="thumb-placeholder">
                                        {j.status === 'running' ? <Loader2 className="spinning" /> : (j.type?.includes('video') ? <Video size={32} /> : <ImageIcon size={32} />)}
                                    </div>
                                )}
                                <div className={`job-status-badge ${j.status}`}>
                                    {j.status === 'succeeded' ? '已完成' : 
                                     j.status === 'failed' ? '失败' : 
                                     j.status === 'running' ? `生成中 ${j.progress ?? 0}%` : '等待中'}
                                </div>
                                {j.status === 'succeeded' && getOutputMedia(j).length > 1 && (
                                    <div className="image-count-badge">
                                        <span>{getOutputMedia(j).length}</span>
                                    </div>
                                )}
                                <div className="card-overlay">
                                    <Maximize size={24} />
                                </div>
                            </div>
                            <div className="job-details">
                                <div className="job-info-row">
                                    <span className="job-workflow-name">{j.workflow_name}</span>
                                    <span className="job-time">{new Date(j.created_at).toLocaleString('zh-CN', { 
                                        month: '2-digit', 
                                        day: '2-digit', 
                                        hour: '2-digit', 
                                        minute: '2-digit' 
                                    })}</span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
                {!hasMore && jobs.length > 0 && <div className="no-more">—— 没有更多记录了 ——</div>}
                {jobs.length === 0 && !loading && (
                    <div className="empty-jobs">
                        <Clock size={48} className="faint-icon" />
                        <p>暂无生成记录</p>
                    </div>
                )}
            </div>

            {viewerJob && (
                <Lightbox
                    items={getOutputMedia(viewerJob)}
                    onClose={() => setViewerJob(null)} 
                    title={viewerJob.workflow_name}
                    workflowType={viewerJob.type}
                    onToast={(title, message) => setToastState({ title, message })}
                />
            )}
        </div>
    );
};

const Lightbox = ({ items, onClose, title, workflowType, onToast }: { items: MediaAsset[], onClose: () => void, title?: string, workflowType?: string, onToast?: (title: string, message: string) => void }) => {
    const [index, setIndex] = useState(0);
    if (items.length === 0) return null;
    const currentItem = items[index];
    const currentKind = resolveMediaKind(currentItem, workflowType);

    return (
        <div className="lightbox-overlay" onClick={onClose}>
            <div className={`lightbox-content ${currentKind === "video" ? "video-mode" : ""}`} onClick={e => e.stopPropagation()}>
                <header className="lightbox-header">
                    <span className="title">{title}</span>
                    <span className="counter">{index + 1} / {items.length}</span>
                    <button className="close-btn" onClick={onClose}><X /></button>
                </header>
                
                <div className="lightbox-main">
                    {items.length > 1 && currentKind !== "video" && (
                        <button className="nav-btn prev" onClick={() => setIndex((index - 1 + items.length) % items.length)}>
                            <ChevronLeft />
                        </button>
                    )}
                    
                    <div className={`image-container ${currentKind === "video" ? "video-container" : ""}`}>
                        {currentKind === "video" ? (
                            <div className="lightbox-video-shell">
                                <VideoPlayer src={currentItem.url} className="lightbox-video-player" />
                            </div>
                        ) : (
                            <img src={currentItem.url} alt="" />
                        )}
                    </div>

                    {items.length > 1 && currentKind !== "video" && (
                        <button className="nav-btn next" onClick={() => setIndex((index + 1) % items.length)}>
                            <ChevronRight />
                        </button>
                    )}
                </div>

                <div className="lightbox-footer">
                   <button
                     type="button"
                     className="download-btn"
                     onClick={async () => {
                       try {
                         const savedPath = await saveRemoteMedia(currentItem);
                         onToast?.("保存成功", `已保存到 ${savedPath}`);
                       } catch (err: any) {
                         const message = err?.message || String(err);
                         onToast?.("保存失败", message);
                         alert(`保存失败: ${message}`);
                       }
                     }}
                   >
                     <Download size={18} />
                     {currentKind === "video" ? "保存视频" : "保存原图"}
                   </button>
                </div>
            </div>
        </div>
    );
};

const APP_VERSION = "0.1.0";

const SettingsView = () => {
    const { user, logout } = useStore();
    return (
        <div className="settings-panel">
            <header className="settings-header">
                <div>
                    <p className="settings-eyebrow">Beikuman AI Studio</p>
                    <h1>设置中心</h1>
                    <p className="settings-subtitle">管理账号信息、应用版本与技术支持入口。</p>
                </div>
            </header>

            <div className="settings-grid">
                <section className="settings-card settings-profile-card">
                    <div className="settings-card-head">
                        <div className="settings-icon-shell">
                            <User size={24} />
                        </div>
                        <div>
                            <h2>账号信息</h2>
                            <p>当前登录的员工账号与身份信息</p>
                        </div>
                    </div>

                    <div className="settings-user-meta">
                        <div className="settings-meta-row">
                            <span className="settings-meta-label">姓名</span>
                            <strong>{user?.name || "未登录"}</strong>
                        </div>
                        <div className="settings-meta-row">
                            <span className="settings-meta-label">账号</span>
                            <strong>{user?.email || "—"}</strong>
                        </div>
                    </div>

                    <button type="button" className="settings-logout-btn" onClick={logout}>
                        <LogOut size={16} />
                        <span>退出登录</span>
                    </button>
                </section>

                <section className="settings-card settings-about-card">
                    <div className="settings-card-head">
                        <div className="settings-icon-shell accent">
                            <Sparkles size={24} />
                        </div>
                        <div>
                            <h2>关于应用</h2>
                            <p>版本信息与技术支持</p>
                        </div>
                    </div>

                    <div className="settings-user-meta compact">
                        <div className="settings-meta-row">
                            <span className="settings-meta-label">当前版本</span>
                            <strong>v{APP_VERSION}</strong>
                        </div>
                        <div className="settings-meta-row align-start">
                            <span className="settings-meta-label">技术支持</span>
                            <div className="settings-support-copy">
                                <strong>兰秋十六</strong>
                                <a href="https://lanqiu.tech" target="_blank" rel="noreferrer">https://lanqiu.tech</a>
                                <span>由兰秋十六提供技术支持</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    );
};

const ImageViewer = ({
  src,
  filename,
  onToast,
}: {
  src: string;
  filename?: string;
  onToast?: (title: string, message: string) => void;
}) => {
  const [scale, setScale] = useState(0.9);
  const download = async () => {
    try {
      const savedPath = await saveRemoteMedia({ url: src, filename });
      onToast?.("下载成功", `图片已下载到 ${savedPath}`);
    } catch (err: any) {
      const message = err?.message || String(err);
      onToast?.("下载失败", message);
      alert(`下载失败: ${message}`);
    }
  };
  return (
    <div className="zoom-viewer">
      <img src={src} style={{ transform: `scale(${scale})` }} alt="result" />
      <div className="bar">
        <button type="button" aria-label="放大图片" onClick={() => setScale(s => s + 0.1)}><ZoomIn size={18} /></button>
        <button type="button" aria-label="缩小图片" onClick={() => setScale(s => s - 0.1)}><ZoomOut size={18} /></button>
        <button type="button" aria-label="保存图片" onClick={download}><Download size={18} /></button>
      </div>
    </div>
  );
};

const VideoViewer = ({
  src,
  filename,
  onToast,
}: {
  src: string;
  filename?: string;
  onToast?: (title: string, message: string) => void;
}) => {
  const download = async () => {
    try {
      const savedPath = await saveRemoteMedia({ url: src, filename });
      onToast?.("下载成功", `视频已下载到 ${savedPath}`);
    } catch (err: any) {
      const message = err?.message || String(err);
      onToast?.("下载失败", message);
      alert(`下载失败: ${message}`);
    }
  };

  return (
    <div className="video-viewer-shell">
      <VideoPlayer src={src} className="video-viewer-player" />
      <div className="video-viewer-actions">
        <button type="button" onClick={download} aria-label="保存视频">
          <Download size={18} />
        </button>
      </div>
    </div>
  );
};

const VideoPosterThumb = ({ src, alt, badge = false }: { src: string; alt: string; badge?: boolean }) => {
  const poster = useVideoPoster(src);

  return (
    <div className="thumb-video-shell">
      {poster ? (
        <img src={poster} alt={alt} />
      ) : (
        <video src={src} muted playsInline preload="auto" />
      )}
      {badge ? <span className="thumb-video-badge"><Video size={14} /></span> : null}
    </div>
  );
};
