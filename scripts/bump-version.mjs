import { readFileSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";

const nextVersion = process.argv[2];

if (!nextVersion || !/^\d+\.\d+\.\d+$/.test(nextVersion)) {
  console.error("Usage: node scripts/bump-version.mjs <major.minor.patch>");
  process.exit(1);
}

const root = resolve(import.meta.dirname, "..");

const packageJsonPath = resolve(root, "package.json");
const cargoTomlPath = resolve(root, "src-tauri/Cargo.toml");
const tauriConfigPath = resolve(root, "src-tauri/tauri.conf.json");

const packageJson = JSON.parse(readFileSync(packageJsonPath, "utf8"));
packageJson.version = nextVersion;
writeFileSync(packageJsonPath, `${JSON.stringify(packageJson, null, 2)}\n`);

const cargoToml = readFileSync(cargoTomlPath, "utf8").replace(
  /^version = ".*"$/m,
  `version = "${nextVersion}"`,
);
writeFileSync(cargoTomlPath, cargoToml);

const tauriConfig = JSON.parse(readFileSync(tauriConfigPath, "utf8"));
tauriConfig.version = nextVersion;
writeFileSync(tauriConfigPath, `${JSON.stringify(tauriConfig, null, 2)}\n`);

console.log(`Updated app version to ${nextVersion}`);
