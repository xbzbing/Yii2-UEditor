/* eslint-env node */
// Parse-side smoke for PR1 (Highcharts -> ECharts).
//
// Serves the repo over http (file:// is an opaque origin for Chrome and would
// mask real problems), renders the fixture with headless Chrome, and asserts
// that every data-chart table was replaced by a live ECharts canvas.
//
// Usage: node docs/local/_smoke/verify-parse.js

const http = require('http');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const REPO = path.resolve(__dirname, '../..');
const PORT = Number(process.env.PORT || 8099);
const CHROME = process.env.CHROME_BIN ||
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const FIXTURE = '/docs/local/_smoke/charts-parse.html';

const MIME = {
    '.html': 'text/html',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
};

const requests = [];

const server = http.createServer((req, res) => {
    requests.push(req.url);
    let p = decodeURIComponent(req.url.split('?')[0]);
    if (p.endsWith('/')) p += 'index.html';
    const file = path.join(REPO, p);
    if (!file.startsWith(REPO) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
        res.writeHead(404);
        res.end('not found');
        return;
    }
    res.writeHead(200, {
        'Content-Type': MIME[path.extname(file)] || 'application/octet-stream',
    });
    fs.createReadStream(file).pipe(res);
});

server.listen(PORT, () => {
    const errors = [];
    const lines = [];

    try {
        const dom = execFileSync(CHROME, [
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--virtual-time-budget=6000',
            '--dump-dom',
            `http://127.0.0.1:${PORT}${FIXTURE}`,
        ], {
            encoding: 'utf8',
            maxBuffer: 128 * 1024 * 1024,
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        fs.writeFileSync(path.join(__dirname, '_last-dom.html'), dom);

        const canvases = (dom.match(/<canvas/gi) || []).length;
        const containers = (dom.match(/class="edui-chart-container/g) || []).length;
        const tables = (dom.match(/data-chart=/gi) || []).length;

        lines.push(`canvas elements       : ${canvases}`);
        lines.push(`chart containers      : ${containers}`);
        lines.push(`residual data-chart   : ${tables}`);
        lines.push(`echarts file requested: ${requests.filter(u => /echarts/.test(u)).join(', ') || '(none)'}`);
        lines.push(`highcharts requested  : ${requests.filter(u => /highcharts/i.test(u)).join(', ') || '(none)'}`);

        if (canvases < 1) errors.push('no <canvas> rendered');
        if (containers < 1) errors.push('no .edui-chart-container');
        if (tables > 0) errors.push('table[data-chart] was not replaced');
        if (requests.some(u => /highcharts/i.test(u))) {
            errors.push('still requesting Highcharts');
        }
        if (!requests.some(u => /echarts/.test(u))) {
            errors.push('echarts was never requested');
        }
    } catch (e) {
        errors.push('chrome run failed: ' + e.message);
    } finally {
        server.close();
    }

    console.log(lines.join('\n'));
    if (errors.length) {
        console.log('\nFAIL:\n' + errors.map(e => ' - ' + e).join('\n'));
        process.exit(1);
    }
    console.log('\nPASS');
    process.exit(0);
});
