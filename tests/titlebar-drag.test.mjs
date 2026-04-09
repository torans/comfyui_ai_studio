import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

test("titlebar title is itself a drag region", () => {
  const appSource = readFileSync(new URL("../src/App.tsx", import.meta.url), "utf8");

  assert.match(
    appSource,
    /className="titlebar-title"[^>]*data-tauri-drag-region|data-tauri-drag-region[^>]*className="titlebar-title"/,
    "the title text should be directly marked as a Tauri drag region",
  );
});

test("window capability explicitly allows manual dragging fallback", () => {
  const capability = JSON.parse(
    readFileSync(new URL("../src-tauri/capabilities/default.json", import.meta.url), "utf8"),
  );

  assert.ok(
    capability.permissions.includes("core:window:allow-start-dragging"),
    "manual startDragging fallback should be explicitly allowed",
  );
});

test("macOS titlebar stays close to native height and offsets traffic lights", () => {
  const appCss = readFileSync(new URL("../src/App.css", import.meta.url), "utf8");
  const tauriConfig = JSON.parse(
    readFileSync(new URL("../src-tauri/tauri.conf.json", import.meta.url), "utf8"),
  );
  const mainWindow = tauriConfig.app.windows[0];

  assert.match(
    appCss,
    /\.custom-titlebar\s*\{[^}]*height:\s*30px;/s,
    "the custom titlebar should stay near native macOS height",
  );
  assert.deepEqual(
    mainWindow.trafficLightPosition,
    { x: 14, y: 14 },
    "traffic lights should be explicitly positioned for overlay title bars",
  );
});
