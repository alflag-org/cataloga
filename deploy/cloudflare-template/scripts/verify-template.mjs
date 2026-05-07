import fs from "node:fs";

const required = [
  "wrangler.toml",
  "package.json",
  "public",
  "worker",
  "worker/shim.mjs",
  "migrations/d1",
];

for (const path of required) {
  if (!fs.existsSync(path)) {
    console.error(`Missing required template path: ${path}`);
    process.exit(1);
  }
}

const nonEmptyDirs = ["public", "migrations/d1"];
for (const dir of nonEmptyDirs) {
  if (fs.readdirSync(dir).length === 0) {
    console.error(`Required directory is empty: ${dir}`);
    process.exit(1);
  }
}

console.log("Cataloga Cloudflare template looks valid.");
