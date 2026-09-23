const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const ROOT_DIR = 'E:\\Projects\\PHP\\Pharma-CRM';
const OUT_DIR = 'E:\\Projects\\PHP\\Pharma-CRM\\docs\\00-inventory';

if (!fs.existsSync(OUT_DIR)) {
    fs.mkdirSync(OUT_DIR, { recursive: true });
}

let fileTree = [];
let gitRepos = [];
let routes = [];
let forms = [];
let dbSchema = [];
let permissions = [];
let workflows = [];
let assetsList = [];
let deadCodeList = [];

function walkDir(dir) {
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
            if (file === '.git') {
                try {
                    const status = cp.execSync('git status -s', { cwd: dir }).toString();
                    const log = cp.execSync('git log -1 --format="%h - %s (%cd)"', { cwd: dir }).toString().trim();
                    const remote = cp.execSync('git remote -v', { cwd: dir }).toString().trim();
                    const branch = cp.execSync('git branch --show-current', { cwd: dir }).toString().trim();
                    gitRepos.push({ path: fullPath, branch, log, remote });
                } catch (e) {
                    gitRepos.push({ path: fullPath, error: 'Failed to read git info' });
                }
                return;
            } else if (file === 'node_modules' || file === 'dist') {
                return; // ignore
            }
            walkDir(fullPath);
        } else {
            fileTree.push({
                path: fullPath.replace(ROOT_DIR, ''),
                size: stat.size,
                mtime: stat.mtime.toISOString(),
                type: classifyFile(fullPath)
            });
            analyzeFile(fullPath);
        }
    });
}

function classifyFile(fullPath) {
    const ext = path.extname(fullPath).toLowerCase();
    if (fullPath.includes('component')) return 'component';
    if (fullPath.includes('route')) return 'route';
    if (fullPath.includes('config')) return 'config';
    if (fullPath.includes('model') || fullPath.includes('type')) return 'model/type';
    if (fullPath.includes('test')) return 'test';
    if (ext === '.css' || ext === '.scss' || ext === '.png' || ext === '.jpg' || ext === '.svg') return 'asset';
    return 'other';
}

function analyzeFile(fullPath) {
    const ext = path.extname(fullPath).toLowerCase();
    if (!['.js', '.jsx', '.ts', '.tsx', '.php', '.html'].includes(ext)) return;
    
    const content = fs.readFileSync(fullPath, 'utf-8');
    
    // Extract routes
    if (content.includes('Route ') || content.includes('path=')) {
        const routeMatches = content.match(/path=['"`](.*?)['"`]/g);
        if (routeMatches) {
            routeMatches.forEach(r => routes.push({ file: fullPath.replace(ROOT_DIR, ''), route: r.replace(/path=|['"`]/g, '') }));
        }
    }

    // Extract forms
    if (content.includes('<form') || content.includes('<Form')) {
        const formFields = content.match(/name=['"`](.*?)['"`]/g) || [];
        forms.push({
            file: fullPath.replace(ROOT_DIR, ''),
            fields: formFields.map(f => f.replace(/name=|['"`]/g, ''))
        });
    }

    // Permissions
    if (content.includes('role') || content.includes('permission') || content.includes('isAdmin')) {
        permissions.push({ file: fullPath.replace(ROOT_DIR, '') });
    }

    // Workflows/Statuses
    if (content.includes('status') || content.match(/enum [A-Za-z]+Status/)) {
        workflows.push({ file: fullPath.replace(ROOT_DIR, '') });
    }
}

walkDir(ROOT_DIR);

// Parse package.json
try {
    const pkg = JSON.parse(fs.readFileSync(path.join(ROOT_DIR, 'package.json'), 'utf-8'));
    assetsList = { ...pkg.dependencies, ...pkg.devDependencies };
} catch(e) {}

fs.writeFileSync(path.join(OUT_DIR, 'tree.md'), JSON.stringify({ fileTree, gitRepos }, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'routes.csv'), 'File,Route\n' + routes.map(r => `"${r.file}","${r.route}"`).join('\n'));
fs.writeFileSync(path.join(OUT_DIR, 'forms.json'), JSON.stringify(forms, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'schema.json'), JSON.stringify(dbSchema, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'permissions.json'), JSON.stringify(permissions, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'workflows.json'), JSON.stringify(workflows, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'assets.json'), JSON.stringify(assetsList, null, 2));
fs.writeFileSync(path.join(OUT_DIR, 'deadcode.md'), 'Automated dead code analysis not full-proof without coverage.\n');

console.log('Inventory done.');
