import { useState, useEffect } from "react";
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
  Sparkles,
  LogOut,
  User,
  Clock,
  Settings2,
  Video,
  ChevronRight,
} from "lucide-react";
import { getCurrentWindow } from "@tauri-apps/api/window";
import { useStore } from "./store";
import { auth, generationJobs, workflowTemplates, comfyUiProxy, WorkflowTemplate } from "./api";
import { listenToJobStatus, disconnectEcho } from "./echo";
import "./App.css";

type Tab = "Workflow" | "Jobs" | "Settings";

// ── App Container ─────────────────────────────────────────────────────────────
export default function App() {
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
               <h3>{workflowsByCategory[selectedCategory]?.label || "选择分类"}</h3>
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
  const [resultImages, setResultImages] = useState<string[]>([]);
  const [currentJobId, setCurrentJobId] = useState<number | null>(null);
  const currentJob = useStore(state => state.jobs.find(j => j.id === currentJobId));

  // 调试输出：看看后端到底给的是什么
  useEffect(() => {
    if (currentWorkflow) {
      console.log("当前工作流完整数据:", currentWorkflow);
    }
  }, [selectedWorkflowId]);

  const getSchema = () => {
    if (!currentWorkflow) return [];
    // 全字段扫描模式
    const possibleFields = ['parameter_schema', 'parameter_schema_json', 'schema', 'inputs', 'data'];
    let s = null;
    for (const field of possibleFields) {
      if ((currentWorkflow as any)[field]) {
        s = (currentWorkflow as any)[field];
        break;
      }
    }
    
    // 如果 s 还是为空，再看看是不是套在了 parameter_schema: { schema: [...] } 里
    if (s && !Array.isArray(s) && typeof s === 'object' && (s as any).schema) {
      s = (s as any).schema;
    }

    // 处理对象格式 (Object -> Array)
    if (s && !Array.isArray(s) && typeof s === 'object') {
      return Object.values(s);
    }

    if (Array.isArray(s)) return s;
    if (typeof s === 'string' && s.trim()) {
      try { 
        const parsed = JSON.parse(s);
        return Array.isArray(parsed) ? parsed : Object.values(parsed);
      } catch (e) { return []; }
    }
    return [];
  };

  const schema = getSchema();

  useEffect(() => {
    if (schema && schema.length > 0) {
      const defaults: Record<string, any> = {};
      schema.forEach((p: any) => { 
        defaults[p.field] = formData[p.field] ?? p.default ?? (p.type === 'number' ? 0 : ""); 
      });
      setFormData(defaults);
    }
  }, [selectedWorkflowId, JSON.stringify(schema)]);

  useEffect(() => {
    if (!currentJob) return;
    if (currentJob.status === 'succeeded') {
      setGenerating(false); setProgress(100);
      if (currentJob.assets) setResultImages(currentJob.assets.filter(a => a.type === 'output').map(a => a.url));
    } else if (currentJob.status === 'failed') {
      setGenerating(false); setProgress(0); alert(`生成失败: ${currentJob.error_message}`);
    } else {
      // 关键修复：只有当后端进度确实增长了，才更新本地状态，防止被旧信号强制拉回
      const newProg = currentJob.progress || 0;
      if (newProg > progress) setProgress(newProg);
    }
  }, [currentJob?.status, currentJob?.progress, currentJob?.assets]);

  // 模拟虚假进度，解决后端轮询延迟导致的进度“卡死”假象
  useEffect(() => {
    let timer: any;
    if (generating && progress < 95) {
      timer = setInterval(() => {
        setProgress(prev => Math.min(95, prev + 1));
      }, 1500);
    }
    return () => clearInterval(timer);
  }, [generating, progress]);

  const handleGenerate = async () => {
    if (generating || !token || !currentWorkflow) return;
    setGenerating(true); setProgress(0); setResultImages([]);
    try {
      const finalInputs = { ...formData };
      for (const p of schema) {
        if (p.type === 'image' && formData[p.field] instanceof File) {
          const file = formData[p.field];
          const arrayBuffer = await file.arrayBuffer();
          const uploadRes = await comfyUiProxy.uploadToAdmin(token, Array.from(new Uint8Array(arrayBuffer)), file.name, file.type);
          finalInputs[p.field] = uploadRes.url;
        }
      }
      const job = await generationJobs.create(token, currentWorkflow.id, finalInputs);
      setCurrentJobId(job.job_id);
    } catch (err: any) { alert(`提交失败: ${err.message}`); setGenerating(false); }
  };

  if (!currentWorkflow) return <div className="full-loading"><Loader2 className="spinning" /></div>;

  return (
    <div className="dynamic-view">
      <section className="params-area">
        <header>
          <h2>{currentWorkflow.name}</h2>
          <p>{currentWorkflow.description || "无描述"}</p>
        </header>

        <div className="fields-scroller">
          {schema.length === 0 && <p className="no-params-hint">未检测到可配置参数</p>}
          {schema.map((p: any) => (
            <div key={p.field} className="form-group">
              <label>{p.label}</label>
              {p.type === 'textarea' || (p.type === 'string' && p.field.includes('prompt')) ? (
                <textarea rows={3} value={formData[p.field] || ""} onChange={e => setFormData({...formData, [p.field]: e.target.value})} />
              ) : p.type === 'number' || p.type === 'integer' ? (
                 <div className="slider-box">
                    <input type="range" min={p.field === 'seed' ? 0 : (p.min ?? 1)} max={p.field === 'seed' ? 1e16 : (p.max ?? 1024)} step={p.step ?? 1} value={formData[p.field] || 0} onChange={e => setFormData({...formData, [p.field]: Number(e.target.value)})} />
                    <input type="number" className="small-num" value={formData[p.field] || 0} onChange={e => setFormData({...formData, [p.field]: Number(e.target.value)})} />
                 </div>
              ) : p.type === 'image' ? (
                 <div className="upload-zone">
                    <input type="file" onChange={e => setFormData({...formData, [p.field]: e.target.files?.[0]})} />
                    <span>{formData[p.field]?.name || "点击更换图片"}</span>
                 </div>
              ) : (
                <input type="text" value={formData[p.field] || ""} onChange={e => setFormData({...formData, [p.field]: e.target.value})} />
              )}
            </div>
          ))}
        </div>

        <div className="action-bar">
          <button className="main-exec-btn" disabled={generating} onClick={handleGenerate}>
            {generating ? <Loader2 className="spinning" /> : <Play fill="currentColor" size={16} />}
            <span>{generating ? `生成中 (${progress}%)` : "开始生成"}</span>
          </button>
        </div>
      </section>

      <section className="display-area">
        {resultImages.length > 0 ? (
           <div className="results-grid">
              {resultImages.map((url, idx) => (
                <div key={idx} className="result-card">
                  <ImageViewer src={url} />
                </div>
              ))}
           </div>
        ) : generating ? (
           <div className="full-center-state">
             <div className="pulse-orb">
               <Sparkles size={64} className="spinning-slow" />
               <div className="prog-val">{progress}%</div>
             </div>
             <h3>画师正在潜心创作...</h3>
             <p>请保持通讯稳定，预计还需一小会儿</p>
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

// ── Shared UI Components ─────────────────────────────────────────────────────
const LoginView = () => {
    const store = useStore();
    const [e, setE] = useState("");
    const [p, setP] = useState("");
    const [l, setL] = useState(false);
    const handleLogin = async () => {
        setL(true);
        try { const res = await auth.login(e, p); store.login(res.token, res.user); }
        catch (err: any) { alert(err.message); }
        finally { setL(false); }
    };
    return (
        <div className="login-full">
            <div className="login-box">
                <div className="logo"><Sparkles size={32} /></div>
                <h1>Beikuman AI Studio</h1>
                <div className="inputs">
                   <input placeholder="员工账号" value={e} onChange={i => setE(i.target.value)} />
                   <input type="password" placeholder="访问密码" value={p} onChange={i => setP(i.target.value)} />
                </div>
                <button onClick={handleLogin} disabled={l}>{l ? "正在认证..." : "登录平台"}</button>
            </div>
        </div>
    );
};

const JobsView = () => {
    const { token, jobs, setJobs } = useStore();
    useEffect(() => { if (token) generationJobs.list(token).then(res => setJobs(res.data)); }, [token]);
    return (
        <div className="simple-panel">
            <header><h1>任务记录</h1></header>
            <div className="list">
                {jobs.map(j => (
                    <div key={j.id} className="row">
                        <span className={`status-dot ${j.status}`} />
                        <div className="info">
                           <span className="name">{j.workflow_name}</span>
                           <span className="date">{new Date(j.created_at).toLocaleString()}</span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

const SettingsView = () => {
    const { user, logout } = useStore();
    return (
        <div className="simple-panel">
            <header><h1>设置</h1></header>
            <div className="user-card">
               <div className="avatar"><User /></div>
               <div className="details">
                  <p><strong>姓名:</strong> {user?.name}</p>
                  <p><strong>账号:</strong> {user?.email}</p>
               </div>
               <button className="out-btn" onClick={logout}>退出登录</button>
            </div>
        </div>
    );
};

const ImageViewer = ({ src }: { src: string }) => {
  const [scale, setScale] = useState(1);
  const download = async () => {
    const r = await fetch(src); const b = await r.blob();
    const u = URL.createObjectURL(b); const l = document.createElement('a');
    l.href = u; l.download = "result.png"; l.click();
  };
  return (
    <div className="zoom-viewer">
      <img src={src} style={{ transform: `scale(${scale})` }} alt="result" />
      <div className="bar">
        <button onClick={() => setScale(s => s + 0.1)}><ZoomIn size={18} /></button>
        <button onClick={() => setScale(s => s - 0.1)}><ZoomOut size={18} /></button>
        <button onClick={download}><Download size={18} /></button>
      </div>
    </div>
  );
};
