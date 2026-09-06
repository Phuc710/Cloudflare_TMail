import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const staticDir = path.join(rootDir, 'static');
const cssOutDir = path.join(staticDir, 'css');
const jsOutDir = path.join(staticDir, 'js');

console.log('🚀 [KaiMail Builder - Node.js] Starting Production Asset Compilation...');

fs.mkdirSync(cssOutDir, { recursive: true });
fs.mkdirSync(jsOutDir, { recursive: true });

// Clean old files
for (const file of fs.readdirSync(cssOutDir)) {
  if (file.endsWith('.css')) fs.unlinkSync(path.join(cssOutDir, file));
}
for (const file of fs.readdirSync(jsOutDir)) {
  if (file.endsWith('.js')) fs.unlinkSync(path.join(jsOutDir, file));
}

function minifyCss(css) {
  return css
    .replace(/\.\.\/assets\//g, '../../assets/')
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/\s*([\{\};:,>])\s*/g, '$1')
    .replace(/\s+/g, ' ')
    .trim();
}

function minifyJs(js) {
  const lines = js
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .split('\n')
    .filter((line) => {
      const trimmed = line.trim();
      return trimmed !== '' && !trimmed.startsWith('//');
    });
  return lines.join('\n');
}

const cssTargets = {
  '/css/home.css': path.join(rootDir, 'css', 'home.css'),
  '/css/admin.css': path.join(rootDir, 'css', 'admin.css'),
};

const jsTargets = {
  '/js/app.js': path.join(rootDir, 'js', 'app.js'),
  '/js/longPolling.js': path.join(rootDir, 'js', 'longPolling.js'),
  '/js/admin.js': path.join(rootDir, 'js', 'admin.js'),
  '/js/admin-dashboard.js': path.join(rootDir, 'js', 'admin-dashboard.js'),
  '/js/admin-login.js': path.join(rootDir, 'js', 'admin-login.js'),
};

const manifest = {};

console.log('\n📦 Compiling Stylesheets:');
for (const [logicalPath, sourcePath] of Object.entries(cssTargets)) {
  if (!fs.existsSync(sourcePath)) continue;
  const raw = fs.readFileSync(sourcePath, 'utf-8');
  const minified = minifyCss(raw);
  const hash = crypto.createHash('sha256').update(minified).digest('hex').slice(0, 10);
  const baseName = path.basename(sourcePath, path.extname(sourcePath));
  const hashedName = `${baseName}.${hash}.min.css`;
  fs.writeFileSync(path.join(cssOutDir, hashedName), minified, 'utf-8');

  const publicPath = `/static/css/${hashedName}`;
  manifest[logicalPath] = publicPath;

  const rawKb = (Buffer.byteLength(raw) / 1024).toFixed(1);
  const minKb = (Buffer.byteLength(minified) / 1024).toFixed(1);
  const saved = ((1 - Buffer.byteLength(minified) / Buffer.byteLength(raw)) * 100).toFixed(1);
  console.log(`   ✓ ${logicalPath} -> ${publicPath} (${rawKb}KB -> ${minKb}KB, -${saved}%)`);
}

console.log('\n⚡ Compiling JavaScript:');
for (const [logicalPath, sourcePath] of Object.entries(jsTargets)) {
  if (!fs.existsSync(sourcePath)) continue;
  const raw = fs.readFileSync(sourcePath, 'utf-8');
  const minified = minifyJs(raw);
  const hash = crypto.createHash('sha256').update(minified).digest('hex').slice(0, 10);
  const baseName = path.basename(sourcePath, path.extname(sourcePath));
  const hashedName = `${baseName}.${hash}.min.js`;
  fs.writeFileSync(path.join(jsOutDir, hashedName), minified, 'utf-8');

  const publicPath = `/static/js/${hashedName}`;
  manifest[logicalPath] = publicPath;

  const rawKb = (Buffer.byteLength(raw) / 1024).toFixed(1);
  const minKb = (Buffer.byteLength(minified) / 1024).toFixed(1);
  const saved = ((1 - Buffer.byteLength(minified) / Buffer.byteLength(raw)) * 100).toFixed(1);
  console.log(`   ✓ ${logicalPath} -> ${publicPath} (${rawKb}KB -> ${minKb}KB, -${saved}%)`);
}

const manifestPath = path.join(staticDir, 'manifest.json');
fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 4), 'utf-8');

console.log('\n📑 Manifest generated at: static/manifest.json');
console.log('🎉 Build finished successfully!\n');
