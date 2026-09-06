const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const errors = [];
    const artifacts = path.join(os.tmpdir(), 'exam-taking-verification');
    fs.mkdirSync(artifacts, { recursive: true });
    try {
        for (const viewport of [{ width: 1366, height: 900 }, { width: 390, height: 844 }]) {
            const page = await browser.newPage({ viewport });
            page.on('pageerror', error => errors.push(error.message));
            let answers = {};
            let submitted;
            let remaining = 3600;
            let pageSize = 2;
            const requests = [];
            await page.route('https://exam-taking.test/**', async route => {
                const request = route.request();
                const url = new URL(request.url());
                requests.push(url.pathname);
                if (url.pathname.startsWith('/utils/')) {
                    const file = path.join(__dirname, '../backend', url.pathname);
                    await route.fulfill({ body: fs.readFileSync(file), contentType: file.endsWith('.js') ? 'application/javascript' : 'text/css' });
                    return;
                }
                if (request.method() === 'POST') {
                    const data = new URLSearchParams(request.postData());
                    for (const [key, value] of data) {
                        if (key.startsWith('question_')) answers[key.slice(9)] = Number(value);
                    }
                    if (url.pathname.endsWith('/submit')) {
                        submitted = Object.fromEntries(data);
                        await route.fulfill({ body: 'Submitted', contentType: 'text/html' });
                    } else {
                        assert.equal(data.get('action'), 'next');
                        const next = Math.min(Number(data.get('current_page')) + 1, Math.ceil(5 / pageSize));
                        await route.fulfill({ status: 303, headers: { location: '/api/exams/1/start/' + next }, body: '' });
                    }
                    return;
                }
                const currentPage = Number(url.pathname.split('/').pop()) || 1;
                const result = spawnSync('php', [path.join(__dirname, 'render-exam-taking.php'), JSON.stringify({ page: currentPage, pageSize, answers, remaining })], { encoding: 'utf8' });
                assert.equal(result.status, 0, result.stderr);
                assert.equal(result.stderr, '');
                await route.fulfill({ body: result.stdout, contentType: 'text/html' });
            });

            const visibleIds = () => page.locator('[data-question-id]:visible').evaluateAll(cards => cards.map(card => Number(card.dataset.questionId)));
            await page.goto('https://exam-taking.test/api/exams/1/start/1');
            await page.waitForFunction(() => document.querySelector('#timerText').textContent !== '--:--:--');
            assert.deepEqual(await visibleIds(), [3, 1]);
            assert.equal(await page.locator('#previousPage').isVisible(), false);
            const publicData = await page.locator('#examData').textContent();
            assert.equal(publicData.includes('is_correct'), false);
            await page.locator('#choice_31').check();
            await page.locator('#choice_11').check();
            await page.locator('#nextPage').click();
            await page.waitForURL('**/start/2');
            assert.deepEqual(await visibleIds(), [5, 2]);
            await page.locator('#choice_51').check();
            const beforePrevious = requests.length;
            await page.locator('#previousPage').click();
            assert.deepEqual(await visibleIds(), [3, 1]);
            assert.equal(requests.length, beforePrevious, 'Previous should paginate locally.');
            assert.equal(await page.locator('#choice_31').isChecked(), true);
            await page.locator('#choice_12').check();
            await page.locator('#nextPage').click();
            await page.waitForFunction(() => document.querySelector('#pageLabel')?.textContent.trim() === 'Page 2 of 3' && document.querySelector('#choice_12')?.checked);
            assert.equal(await page.locator('#choice_51').isChecked(), true);
            await page.locator('#choice_21').check();
            await page.locator('#nextPage').click();
            await page.waitForURL('**/start/3');
            assert.deepEqual(await visibleIds(), [4]);
            await page.locator('#choice_41').check();
            assert.equal(await page.locator('#answeredQuestionCount').textContent(), '5');
            assert.equal(await page.locator('#unansweredQuestionCount').textContent(), '0');
            assert.equal(await page.locator('#nextPage').isVisible(), false);
            const hasOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            assert.equal(hasOverflow, false, 'Exam page overflows the viewport.');
            await page.screenshot({ path: path.join(artifacts, `exam-${viewport.width}.png`), fullPage: true });
            await page.locator('#lastPageSubmit').click();
            await page.locator('#confirmSubmit').click();
            await page.waitForURL('**/submit');
            assert.equal(submitted.question_4, '41', 'Final page answer is missing.');
            assert.equal(submitted.question_1, '12', 'Revised answer was not submitted.');
            assert.equal(Object.keys(submitted).filter(key => key.startsWith('question_')).length, 5);

            answers = { 3: 31 };
            submitted = undefined;
            remaining = 0;
            await page.goto('https://exam-taking.test/api/exams/1/start/1');
            await page.waitForURL('**/submit');
            assert.equal(submitted.question_3, '31', 'Timer submission lost saved answers.');
            remaining = 3600;
            pageSize = 3;
            await page.goto('https://exam-taking.test/api/exams/1/start/2');
            assert.deepEqual(await visibleIds(), [2, 4]);
            await page.close();
            console.log(`PASS: ${viewport.width}px navigation, saves, final submit, timer submit, configurable page size`);
        }
        assert.deepEqual(errors, [], 'Browser JavaScript errors');
        console.log('Screenshots: ' + artifacts);
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
