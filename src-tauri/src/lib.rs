use std::path::Path;
use std::time::{SystemTime, UNIX_EPOCH};

use serde::{Deserialize, Serialize};
use tauri::{AppHandle, Manager};

#[derive(Serialize, Deserialize)]
struct ComfyPromptRequest {
    prompt: serde_json::Value,
    client_id: String,
}

#[derive(Serialize, Deserialize)]
struct LoginRequest {
    email: String,
    password: String,
}

fn build_direct_client(timeout_secs: Option<u64>) -> Result<reqwest::Client, String> {
    let mut builder = reqwest::Client::builder().no_proxy();

    if let Some(timeout_secs) = timeout_secs {
        builder = builder.timeout(std::time::Duration::from_secs(timeout_secs));
    }

    builder
        .build()
        .map_err(|e| format!("Failed to build HTTP client: {}", e))
}

#[tauri::command]
async fn send_comfy_prompt(server_url: String, prompt: serde_json::Value, client_id: String) -> Result<serde_json::Value, String> {
    // 1. Normalize server URL (remove trailing slashes)
    let base_url = server_url.trim_end_matches('/');
    let full_url = format!("{}/prompt", base_url);

    println!(">>> Sending prompt to ComfyUI: {}", full_url);
    println!(">>> Client ID: {}", client_id);

    let client = build_direct_client(Some(30))?;

    // Log the actual model being used for debugging
    if let Some(ckpt) = prompt.pointer("/4/inputs/ckpt_name") {
        println!(">>> Using Model: {}", ckpt);
    }

    let payload = ComfyPromptRequest {
        prompt,
        client_id,
    };

    let response = client
        .post(&full_url)
        .json(&payload)
        .send()
        .await
        .map_err(|e| {
            let err_msg = format!("Network error connecting to ComfyUI: {}", e);
            println!("!!! Error: {}", err_msg);
            err_msg
        })?;

    let status = response.status();
    if status.is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse ComfyUI response: {}", e))?;
        println!("<<< ComfyUI Success Response received");
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        let err_msg = format!("ComfyUI returned error {}: {}", status, err_text);
        println!("!!! Error: {}", err_msg);
        Err(err_msg)
    }
}

#[tauri::command]
async fn get_comfy_models(server_url: String) -> Result<Vec<String>, String> {
    let base_url = server_url.trim_end_matches('/');
    let client = build_direct_client(None)?;
    let url = format!("{}/object_info", base_url);

    let response = client.get(&url).send().await.map_err(|e| e.to_string())?;
    let info: serde_json::Value = response.json().await.map_err(|e| e.to_string())?;

    // Extract checkpoints from CheckpointLoaderSimple or similar loaders
    // In ComfyUI, CheckpointLoaderSimple.input.required.ckpt_name[0] usually contains the list
    if let Some(nodes) = info.as_object() {
        if let Some(loader) = nodes.get("CheckpointLoaderSimple") {
            if let Some(names) = loader.pointer("/input/required/ckpt_name/0") {
                if let Some(list) = names.as_array() {
                    return Ok(list.iter().filter_map(|v| v.as_str().map(|s| s.to_string())).collect());
                }
            }
        }
    }

    // Fallback or generic model hunting if CheckpointLoaderSimple isn't found
    Ok(vec![])
}

#[tauri::command]
async fn api_login(admin_url: String, email: String, password: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/login", base_url);

    println!(">>> Login request to: {}", full_url);

    let client = build_direct_client(Some(30))?;

    let payload = LoginRequest { email, password };

    let response = client
        .post(&full_url)
        .header("Accept", "application/json")
        .json(&payload)
        .send()
        .await
        .map_err(|e| {
            let err_msg = format!("Network error: {}", e);
            println!("!!! Error: {}", err_msg);
            err_msg
        })?;

    let status = response.status();
    if status.is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse response: {}", e))?;
        println!("<<< Login success");
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        let err_msg = format!("Login failed {}: {}", status, err_text);
        println!("!!! Error: {}", err_msg);
        Err(err_msg)
    }
}

#[tauri::command]
async fn api_get_workflows(admin_url: String, token: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/workflow-templates", base_url);

    let client = build_direct_client(None)?;

    let response = client
        .get(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        // Wrap in data field to match frontend expectation
        Ok(serde_json::json!({ "data": json }))
    } else {
        Err(format!("Request failed: {}", response.status()))
    }
}

#[tauri::command]
async fn api_create_job(admin_url: String, token: String, workflow_id: u32, inputs: serde_json::Value, client_request_id: Option<String>) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/generation-jobs", base_url);

    let client = build_direct_client(None)?;

    let mut payload = serde_json::json!({
        "workflow_id": workflow_id,
        "inputs": inputs
    });

    // 添加 client_request_id 如果提供
    if let Some(req_id) = client_request_id {
        payload["client_request_id"] = serde_json::json!(req_id);
    }

    let response = client
        .post(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .json(&payload)
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        Err(format!("创建任务失败: {}", err_text))
    }
}

#[tauri::command]
async fn api_get_jobs(admin_url: String, token: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/generation-jobs", base_url);

    let client = build_direct_client(None)?;

    let response = client
        .get(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        Err(format!("获取任务列表失败: {}", response.status()))
    }
}

#[tauri::command]
async fn api_get_job(admin_url: String, token: String, job_id: u32) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/generation-jobs/{}", base_url, job_id);

    let client = build_direct_client(None)?;

    let response = client
        .get(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        Err(format!("获取任务详情失败: {}", response.status()))
    }
}

#[tauri::command]
async fn api_comfyui_system_stats(admin_url: String, token: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/comfyui/system-stats", base_url);

    let client = build_direct_client(None)?;

    let response = client
        .get(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        Err(format!("获取 ComfyUI 状态失败: {}", response.status()))
    }
}

#[tauri::command]
async fn api_comfyui_models(admin_url: String, token: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/comfyui/models", base_url);

    let client = build_direct_client(None)?;

    let response = client
        .get(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        Err(format!("获取模型列表失败: {}", response.status()))
    }
}

#[tauri::command]
async fn api_upload_image_to_comfyui(admin_url: String, token: String, path: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/comfyui/upload-image", base_url);

    let client = build_direct_client(None)?;

    let payload = serde_json::json!({
        "path": path
    });

    let response = client
        .post(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .json(&payload)
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        Err(format!("上传图片失败: {}", err_text))
    }
}

#[tauri::command]
async fn api_upload_to_admin(admin_url: String, token: String, file_data: Vec<u8>, file_name: String, mime_type: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/uploads/images", base_url);

    let client = build_direct_client(None)?;

    let part = reqwest::multipart::Part::bytes(file_data)
        .file_name(file_name)
        .mime_str(&mime_type)
        .map_err(|e| format!("Invalid MIME type: {}", e))?;

    let form = reqwest::multipart::Form::new()
        .part("file", part);

    let response = client
        .post(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .multipart(form)
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        Err(format!("上传到 Admin 失败: {}", err_text))
    }
}

#[tauri::command]
async fn api_upload_workflow_image(admin_url: String, token: String, file_data: Vec<u8>, file_name: String, mime_type: String) -> Result<serde_json::Value, String> {
    let base_url = admin_url.trim_end_matches('/');
    let full_url = format!("{}/api/comfyui/uploads/images", base_url);

    let client = build_direct_client(Some(180))?;

    let part = reqwest::multipart::Part::bytes(file_data)
        .file_name(file_name)
        .mime_str(&mime_type)
        .map_err(|e| format!("Invalid MIME type: {}", e))?;

    let form = reqwest::multipart::Form::new()
        .part("file", part);

    let response = client
        .post(&full_url)
        .header("Authorization", format!("Bearer {}", token))
        .multipart(form)
        .send()
        .await
        .map_err(|e| format!("Network error: {}", e))?;

    if response.status().is_success() {
        let json: serde_json::Value = response.json().await.map_err(|e| format!("Failed to parse: {}", e))?;
        Ok(json)
    } else {
        let err_text = response.text().await.unwrap_or_else(|_| "Unknown error".to_string());
        Err(format!("上传工作流图片失败: {}", err_text))
    }
}

fn sanitize_filename_component(value: &str) -> String {
    value
        .chars()
        .map(|ch| match ch {
            '/' | '\\' | ':' | '*' | '?' | '"' | '<' | '>' | '|' => '_',
            c if c.is_control() => '_',
            c => c,
        })
        .collect::<String>()
        .trim()
        .trim_matches('.')
        .to_string()
}

fn extension_from_content_type(content_type: &str) -> Option<&'static str> {
    let lowered = content_type.to_ascii_lowercase();

    if lowered.contains("png") {
        Some("png")
    } else if lowered.contains("jpeg") || lowered.contains("jpg") {
        Some("jpg")
    } else if lowered.contains("webp") {
        Some("webp")
    } else if lowered.contains("gif") {
        Some("gif")
    } else if lowered.contains("mp4") {
        Some("mp4")
    } else if lowered.contains("webm") {
        Some("webm")
    } else if lowered.contains("quicktime") || lowered.contains("mov") {
        Some("mov")
    } else {
        None
    }
}

fn infer_extension(url: &str, headers: &reqwest::header::HeaderMap, suggested_filename: Option<&str>) -> String {
    let from_filename = suggested_filename
        .and_then(|name| Path::new(name).extension())
        .and_then(|ext| ext.to_str())
        .map(|ext| ext.trim().trim_start_matches('.').to_ascii_lowercase())
        .filter(|ext| !ext.is_empty());

    if let Some(extension) = from_filename {
        return extension;
    }

    let from_url = reqwest::Url::parse(url)
        .ok()
        .and_then(|parsed| parsed.path_segments().and_then(|segments| segments.last().map(|segment| segment.to_string())))
        .and_then(|segment| Path::new(&segment).extension().and_then(|ext| ext.to_str()).map(|ext| ext.to_ascii_lowercase()))
        .filter(|ext| !ext.is_empty());

    if let Some(extension) = from_url {
        return extension;
    }

    headers
        .get(reqwest::header::CONTENT_TYPE)
        .and_then(|value| value.to_str().ok())
        .and_then(extension_from_content_type)
        .unwrap_or("bin")
        .to_string()
}

fn build_download_filename(url: &str, suggested_filename: Option<&str>, extension: &str) -> String {
    let suggested_stem = suggested_filename
        .and_then(|name| Path::new(name).file_stem())
        .and_then(|stem| stem.to_str())
        .map(sanitize_filename_component)
        .filter(|stem| !stem.is_empty());

    let url_stem = reqwest::Url::parse(url)
        .ok()
        .and_then(|parsed| parsed.path_segments().and_then(|segments| segments.last().map(|segment| segment.to_string())))
        .and_then(|segment| Path::new(&segment).file_stem().and_then(|stem| stem.to_str()).map(sanitize_filename_component))
        .filter(|stem| !stem.is_empty());

    let stem = suggested_stem.or(url_stem).unwrap_or_else(|| {
        let timestamp = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .map(|duration| duration.as_secs())
            .unwrap_or_default();
        format!("beikuman_{}_{}", timestamp, uuid::Uuid::new_v4().simple())
    });

    format!("{}.{}", stem, extension)
}

#[tauri::command]
async fn api_download_remote_media(
    app: AppHandle,
    url: String,
    suggested_filename: Option<String>,
) -> Result<serde_json::Value, String> {
    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(120))
        .build()
        .map_err(|e| format!("Failed to build HTTP client: {}", e))?;

    let response = client
        .get(&url)
        .send()
        .await
        .map_err(|e| format!("下载媒体失败: {}", e))?;

    if !response.status().is_success() {
        return Err(format!("下载媒体失败: {}", response.status()));
    }

    let headers = response.headers().clone();
    let bytes = response
        .bytes()
        .await
        .map_err(|e| format!("读取媒体内容失败: {}", e))?;

    let download_root = app
        .path()
        .download_dir()
        .map_err(|e| format!("无法定位下载目录: {}", e))?;
    let download_dir = download_root.join("Beikuman AI Studio");
    std::fs::create_dir_all(&download_dir).map_err(|e| format!("创建下载目录失败: {}", e))?;

    let extension = infer_extension(&url, &headers, suggested_filename.as_deref());
    let filename = build_download_filename(&url, suggested_filename.as_deref(), &extension);
    let saved_path = download_dir.join(filename);

    std::fs::write(&saved_path, &bytes).map_err(|e| format!("保存媒体失败: {}", e))?;

    Ok(serde_json::json!({
        "saved_path": saved_path.to_string_lossy().to_string()
    }))
}

#[tauri::command]
async fn open_devtools(app: AppHandle) -> Result<(), String> {
    if let Some(window) = app.get_webview_window("main") {
        window.open_devtools();
        Ok(())
    } else {
        Err("Main window not found".to_string())
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![send_comfy_prompt, get_comfy_models, api_login, api_get_workflows, api_create_job, api_get_jobs, api_get_job, api_comfyui_system_stats, api_comfyui_models, api_upload_image_to_comfyui, api_upload_to_admin, api_upload_workflow_image, api_download_remote_media, open_devtools])
        .setup(|_app| {
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
