// 啟動 PHP 內建伺服器前先刪除 storage/demo.json，確保每次測試都從乾淨種子開始。
import { spawn } from 'node:child_process';
import { existsSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');
const demoFile = resolve(root, 'storage', 'demo.json');

if (existsSync(demoFile)) {
  rmSync(demoFile, { force: true });
  console.log('[start-server] removed', demoFile);
}

const child = spawn('php', ['-S', 'localhost:8000', '-t', 'public'], {
  cwd: root,
  stdio: 'inherit',
  shell: false,
  env: { ...process.env, FORMHUB_DEMO: '1' },
});

child.on('exit', (code) => process.exit(code ?? 0));
process.on('SIGINT', () => child.kill('SIGINT'));
process.on('SIGTERM', () => child.kill('SIGTERM'));
