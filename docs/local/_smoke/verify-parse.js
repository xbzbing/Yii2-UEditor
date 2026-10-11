/* eslint-env node */
// Parse-side smoke for the Highcharts -> ECharts migration.
//
// Serves the repo over http (file:// is an opaque origin for Chrome and would
// mask real problems), renders both the source and the minified fixture with
// headless Chrome, and asserts that every data-chart table became a live
// ECharts chart WITH REAL DATA — not merely that a <canvas> exists. An empty
// chart still produces a canvas and an instance, so data-level assertions
// (series count, per-type areaStyle / category axis / pie mode) are what
// actually guards against regressions like the minified "series is always
// empty" bug.
//
// Usage: node docs/local/_smoke/verify-parse.js
//   PORT=8099        override the base port (auto-increments if taken)
//   CHROME_BIN=...   path to a Chrome/Chromium binary

const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn } = require('child_process');

// Repo root is three levels up from docs/local/_smoke.
const REPO = path.resolve(__dirname, '../../..');
const BASE_PORT = Number(process.env.PORT || 8099);
const CHROME = process.env.CHROME_BIN ||
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const RENDER_TIMEOUT_MS = 45000;

// Both fixtures render the same four representative chart types; the min
// fixture only differs in which parser it loads.
const FIXTURES = [
    '/docs/local/_smoke/charts-parse.html',
    '/docs/local/_smoke/charts-parse-min.html',
];

// Expectations per chart, matched positionally to the fixture tables.
// pieSweep guards the pie's data shape: ECharts silently renders nothing when
// pie data is [name, value] instead of {name, value}, and the option still
// looks well-formed, so type/data-length assertions alone miss it.
const EXPECTED = [
    { label: 'line (chartType 0)', type: 'line' },
    { label: 'area (chartType 2)', type: 'line', areaStyle: true },
    { label: 'bar horizontal (chartType 3)', type: 'bar', yAxisType: 'category', yAxisHasData: true },
    { label: 'pie (chartType 5)', type: 'pie', selectedMode: 'single', pieSweep: true },
];

const MIME = {
    '.html': 'text/html',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
};

function listen(server, port, attempt) {
    return new Promise((resolve, reject) => {
        const onError = (e) => {
            server.removeListener('listening', onListening);
            if (e.code === 'EADDRINUSE' && attempt < 10) {
                resolve(listen(server, port + 1, attempt + 1));
            } else {
                reject(e);
            }
        };
        const onListening = () => {
            server.removeListener('error', onError);
            resolve(port);
        };
        server.once('error', onError);
        server.once('listening', onListening);
        server.listen(port);
    });
}

function renderFixture(port, fixture) {
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
        res.writeHead(200, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream' });
        fs.createReadStream(file).pipe(res);
    });

    return listen(server, port, 0).then((boundPort) => {
        const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'ueditor-smoke-'));
        const domFile = path.join(workDir, 'dom.html');
        const out = fs.openSync(domFile, 'w');

        // Render with an ASYNC child so this process's event loop keeps serving
        // the fixture's HTTP requests while Chrome runs (a synchronous spawn
        // would block the server and deadlock the page load). --user-data-dir
        // isolates the run so Chrome does not attach to a running instance; some
        // macOS Chrome builds then refuse to exit after dumping, so instead of
        // waiting for exit we poll the dumped DOM and kill Chrome as soon as the
        // fixture has reported its result.
        return new Promise((resolve) => {
            const child = spawn(CHROME, [
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                `--user-data-dir=${workDir}/profile`,
                // Needs enough virtual budget for the async echarts + config load
                // chain AND the fixture's 2500ms settle timer; poll-and-kill below
                // exits as soon as the result appears, so a generous budget is free.
                '--virtual-time-budget=15000',
                '--dump-dom',
                `http://127.0.0.1:${boundPort}${fixture}`,
            ], { stdio: ['ignore', out, 'ignore'] });

            let settled = false;
            const finish = (via) => {
                if (settled) return;
                settled = true;
                clearInterval(poll);
                clearTimeout(killer);
                try { child.kill('SIGKILL'); } catch (e) { /* gone */ }
                try { fs.closeSync(out); } catch (e) { /* already closed */ }
                let dom = '';
                try { dom = fs.readFileSync(domFile, 'utf8'); } catch (e) { /* none */ }
                if (process.env.SMOKE_DEBUG) {
                    process.stderr.write(`[debug] via=${via} reqs=${requests.length} bytes=${dom.length} RESULT=${/RESULT:/.test(dom)}\n`);
                }
                server.close();
                fs.rmSync(workDir, { recursive: true, force: true });
                fs.writeFileSync(path.join(os.tmpdir(), 'ueditor-smoke-last-dom.html'), dom);
                resolve({ dom, requests });
            };

            // The fixture writes RESULT:/NO_ECHARTS/_ERR into #smoke-result once done.
            // Poll for it and kill Chrome as soon as it appears; do not wait for
            // Chrome to exit on its own (headless may linger after --dump-dom).
            const poll = setInterval(() => {
                let dom = '';
                try { dom = fs.readFileSync(domFile, 'utf8'); } catch (e) { return; }
                if (/smoke-result">(RESULT:|NO_ECHARTS|UPARSE_ERR|FINAL_ERR)/.test(dom)) finish('poll');
            }, 200);

            const killer = setTimeout(() => finish('timeout'), RENDER_TIMEOUT_MS);
            child.on('error', (e) => finish('error:' + e.message));
        });
    });
}

function parseResult(dom) {
    const m = dom.match(/<pre id="smoke-result">([\s\S]*?)<\/pre>/);
    if (!m) return { raw: '(no smoke-result element)' };
    const text = m[1]
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
    if (text.indexOf('RESULT:') !== 0) return { raw: text };
    try {
        return { data: JSON.parse(text.slice('RESULT:'.length)) };
    } catch (e) {
        return { raw: text, parseError: e.message };
    }
}

function assertFixture(dom, requests) {
    const errors = [];
    const { data, raw, parseError } = parseResult(dom);

    if (!data) {
        errors.push(`no parseable result (${parseError || raw})`);
        return errors;
    }
    if (requests.some((u) => /highcharts/i.test(u))) errors.push('still requesting Highcharts');
    if (!requests.some((u) => /echarts/.test(u))) errors.push('echarts was never requested');
    if (data.residualTables > 0) errors.push(`${data.residualTables} data-chart table(s) not replaced`);
    if (data.containers !== EXPECTED.length) {
        errors.push(`expected ${EXPECTED.length} chart containers, got ${data.containers}`);
    }

    EXPECTED.forEach((exp, i) => {
        const c = (data.charts || [])[i];
        const where = `chart#${i} ${exp.label}`;
        if (!c || !c.instance) { errors.push(`${where}: no ECharts instance`); return; }
        if (!(c.seriesCount >= 1)) errors.push(`${where}: series is empty (seriesCount=${c.seriesCount})`);
        if (!(c.s0DataLen >= 1)) errors.push(`${where}: first series has no data points`);
        if (exp.type && c.s0Type !== exp.type) errors.push(`${where}: expected series type ${exp.type}, got ${c.s0Type}`);
        if (exp.areaStyle && !c.s0HasAreaStyle) errors.push(`${where}: area chart lost areaStyle`);
        if (exp.yAxisType && c.yAxisType !== exp.yAxisType) errors.push(`${where}: expected yAxis type ${exp.yAxisType}, got ${c.yAxisType}`);
        if (exp.yAxisHasData && !c.yAxisHasData) errors.push(`${where}: category axis has no category names`);
        if (exp.selectedMode && c.s0SelectedMode !== exp.selectedMode) errors.push(`${where}: expected selectedMode ${exp.selectedMode}, got ${c.s0SelectedMode}`);
        // A full pie sweeps 360°; 0/NaN/-1 means the sectors were never laid out
        // (e.g. pie data given as [name, value] instead of {name, value}).
        if (exp.pieSweep && !(c.pieSweep >= 350)) errors.push(`${where}: pie drew no sectors (sweep=${c.pieSweep}°)`);
    });

    return errors;
}

(async () => {
    const port = BASE_PORT;
    const allErrors = [];

    for (const fixture of FIXTURES) {
        process.stdout.write(`\n### ${fixture}\n`);
        try {
            const { dom, requests } = await renderFixture(port, fixture);
            const { data, raw } = parseResult(dom);
            console.log(data ? JSON.stringify(data, null, 2) : raw);
            const errors = assertFixture(dom, requests);
            if (errors.length) {
                allErrors.push(...errors.map((e) => `${fixture}: ${e}`));
                console.log('FAIL:\n' + errors.map((e) => ' - ' + e).join('\n'));
            } else {
                console.log('PASS');
            }
        } catch (e) {
            allErrors.push(`${fixture}: render failed - ${e.message}`);
            console.log('render failed: ' + e.message);
        }
    }

    console.log('\n========================================');
    if (allErrors.length) {
        console.log(`SMOKE FAILED (${allErrors.length} issue(s))`);
        process.exit(1);
    }
    console.log('SMOKE PASSED');
    process.exit(0);
})();
