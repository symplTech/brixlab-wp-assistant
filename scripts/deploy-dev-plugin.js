#!/usr/bin/env node
const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');

try {
  require('dotenv').config({ path: path.resolve(__dirname, '..', '.env') });
} catch (e) {}

const PLUGIN_NAME = 'brixlab-assistant';
const DEV_SRC = path.resolve(__dirname, '..', 'dist', 'Develop', PLUGIN_NAME);

async function exists(p) {
  try { await fsp.access(p); return true; } catch { return false; }
}

async function rmrf(target) {
  if (!(await exists(target))) return;
  await fsp.rm(target, { recursive: true, force: true });
}

async function copyDir(src, dest) {
  await fs.promises.cp(src, dest, { recursive: true, force: true });
}

(async () => {
  const targetRoot = process.env.DEV_WORDPRESS_PLUGIN_PATH;
  if (!targetRoot) {
    console.log('[deploy] DEV_WORDPRESS_PLUGIN_PATH not set. Skipping.');
    return;
  }

  if (!(await exists(DEV_SRC))) {
    console.log(`[deploy] Source not found: ${DEV_SRC}. Skipping.`);
    return;
  }

  const dest = path.resolve(targetRoot, PLUGIN_NAME);
  console.log(`[deploy] ${PLUGIN_NAME}: deploying to ${dest}`);
  await rmrf(dest);
  await copyDir(DEV_SRC, dest);
  console.log(`[deploy] Done at ${new Date().toLocaleString()}`);
})().catch((err) => {
  console.error('[deploy] Error:', err);
  process.exitCode = 1;
});
