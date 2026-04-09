use serde::{Deserialize, Serialize};

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

#[tauri::command]
async fn send_comfy_prompt(server_url: String, prompt: serde_json::Value, client_id: String) -> Result<serde_json::Value, String> {
    // 1. Normalize server URL (remove trailing slashes)
    let base_url = server_url.trim_end_matches('/');
    let full_url = format!("{}/prompt", base_url);

    println!(">>> Sending prompt to ComfyUI: {}", full_url);
    println!(">>> Client ID: {}", client_id);

    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(30))
        .build()
        .map_err(|e| format!("Failed to build HTTP client: {}", e))?;

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
    let client = reqwest::Client::new();
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

    let client = reqwest::Client::builder()
        .timeout(std::time::Duration::from_secs(30))
        .build()
        .map_err(|e| format!("Failed to build HTTP client: {}", e))?;

    let payload = LoginRequest { email, password };

    let response = client
        .post(&full_url)
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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

    let client = reqwest::Client::new();

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

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![send_comfy_prompt, get_comfy_models, api_login, api_get_workflows, api_create_job, api_get_jobs, api_get_job, api_comfyui_system_stats, api_comfyui_models, api_upload_image_to_comfyui, api_upload_to_admin])
        .setup(|_app| {
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
}
