/**
 * Moocker screenshot generator.
 *
 * Logs into a running Moodle instance and captures the pages referenced from
 * the root README into `docs/screenshots`.
 *
 * Configuration (all optional):
 *   BASE_URL            Base URL of the instance (default http://localhost:24430)
 *   DEMO_COURSE_ID      Course id used for the course page capture
 *   DEMO_CATEGORY_ID    Category id used for the course management capture
 *   ACCOUNT_LOGIN       Admin username (default `admin`)
 *   ACCOUNT_PASSWORD    Admin password (default `Moocker-Screenshots-2026!`)
 *   OUTPUT_DIR          Where the PNGs are written (default this folder)
 *   PW_CHANNEL          Playwright browser channel (default `chrome`)
 */
import { mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const here = path.dirname(fileURLToPath(import.meta.url));
const BASE_URL = (process.env.BASE_URL ?? 'http://localhost:24430').replace(/\/+$/, '');
const DEMO_COURSE_ID = process.env.DEMO_COURSE_ID ?? '';
const DEMO_CATEGORY_ID = process.env.DEMO_CATEGORY_ID ?? '';
const ACCOUNT_LOGIN = process.env.ACCOUNT_LOGIN ?? 'admin';
const ACCOUNT_PASSWORD = process.env.ACCOUNT_PASSWORD ?? 'Moocker-Screenshots-2026!';
const OUTPUT_DIR = process.env.OUTPUT_DIR ? path.resolve(process.env.OUTPUT_DIR) : here;
const PW_CHANNEL = process.env.PW_CHANNEL ?? 'chrome';
const VIEWPORT = { width: 1440, height: 900 };

const log = message => console.log(`[screenshots] ${message}`);

const waitForInstance = async (timeoutMs = 180_000) => {
	const deadline = Date.now() + timeoutMs;
	for (;;) {
		try {
			const response = await fetch(`${BASE_URL}/login/index.php`, { signal: AbortSignal.timeout(5000) });
			if (response.ok) return;
		} catch {
			// Not up yet.
		}
		if (Date.now() > deadline) throw new Error(`instance did not become ready at ${BASE_URL}`);
		await new Promise(resolve => setTimeout(resolve, 2000));
	}
};

const waitForImages = async target => {
	await target.evaluate(
		() =>
			new Promise(resolve => {
				const pending = [...document.images].filter(image => !image.complete);
				if (!pending.length) return resolve();
				let remaining = pending.length;
				const done = () => {
					remaining -= 1;
					if (remaining === 0) resolve();
				};

				for (const image of pending) {
					image.addEventListener('load', done, { once: true });
					image.addEventListener('error', done, { once: true });
				}

				setTimeout(resolve, 5000);
			})
	);
};

const main = async () => {
	await mkdir(OUTPUT_DIR, { recursive: true });
	await waitForInstance();

	const browser = await chromium.launch({
		channel: PW_CHANNEL,
		headless: true,
		args: ['--no-sandbox', '--disable-dev-shm-usage']
	});

	const context = await browser.newContext({
		viewport: VIEWPORT,
		deviceScaleFactor: 1,
		locale: 'en-US',
		colorScheme: 'light'
	});
	const page = await context.newPage();

	const shot = async (name, url, { target = page, waitFor, ready, fullPage = false, settle = 1200 } = {}) => {
		await target.goto(`${BASE_URL}${url}`, { waitUntil: 'domcontentloaded', timeout: 35_000 });

		if (waitFor) {
			try {
				await target.waitForSelector(waitFor, { timeout: 15_000 });
			} catch {
				log(`warning: ${waitFor} did not appear on ${url}`);
			}
		}

		if (ready) {
			try {
				await target.waitForFunction(ready, null, { timeout: 20_000 });
			} catch {
				log(`warning: page did not become ready on ${url}`);
			}
		}

		await waitForImages(target);
		await target.waitForTimeout(settle);
		await target.screenshot({ path: path.join(OUTPUT_DIR, `${name}.png`), fullPage });
		log(`captured ${name}.png`);
	};

	// Moodle renders blocks asynchronously; wait until the visible skeleton
	// placeholders are gone so screenshots show real content instead of loading
	// bars. Hidden skeleton templates stay in the DOM, hence the visibility test.
	const noSkeletons = () =>
		!Array.from(document.querySelectorAll('.bg-pulse-grey')).some(
			element => element.getClientRects().length > 0
		);

	// Login page: use a separate, anonymous context so the admin session is not
	// already active when the form is photographed.
	const anonymous = await browser.newContext({
		viewport: VIEWPORT,
		deviceScaleFactor: 1,
		locale: 'en-US',
		colorScheme: 'light'
	});
	const anonymousPage = await anonymous.newPage();
	await shot('login', '/login/index.php', { target: anonymousPage, waitFor: '#username' });
	await anonymous.close();

	log('logging in');
	// Log in through the context's request client. Moodle verifies the session by
	// redirecting to login/index.php?testsession=<id>; doing this in the HTTP
	// client is deterministic and the browser then reuses the same cookies.
	const api = context.request;
	const loginPage = await api.get(`${BASE_URL}/login/index.php`);
	const loginHtml = await loginPage.text();
	const loginToken = loginHtml.match(/name="logintoken" value="([^"]+)"/)?.[1];
	if (!loginToken) {
		throw new Error('login token not found on the login page');
	}

	const loginResponse = await api.post(`${BASE_URL}/login/index.php`, {
		form: {
			username: ACCOUNT_LOGIN,
			password: ACCOUNT_PASSWORD,
			logintoken: loginToken
		},
		maxRedirects: 0
	});
	const redirect = loginResponse.headers()['location'] ?? '';
	if (loginResponse.status() !== 303 || redirect.includes('loginredirect=1')) {
		throw new Error('login failed; check ACCOUNT_LOGIN/ACCOUNT_PASSWORD');
	}

	const sessionCheck = await api.get(redirect);
	if (!sessionCheck.ok() || sessionCheck.url().includes('/login/')) {
		throw new Error('login session was not established');
	}
	log('logged in');

	await shot('dashboard', '/my/', { waitFor: '#page-content', ready: noSkeletons });
	await shot('courses', '/my/courses.php', { waitFor: '#page-content', ready: noSkeletons });

	if (DEMO_COURSE_ID) {
		await shot('course', `/course/view.php?id=${DEMO_COURSE_ID}`, { waitFor: '#page-content', ready: noSkeletons });
	} else {
		log('DEMO_COURSE_ID not set, skipping course.png');
	}

	const managementUrl = DEMO_CATEGORY_ID
		? `/course/management.php?categoryid=${DEMO_CATEGORY_ID}`
		: '/course/management.php';
	await shot('course-management', managementUrl, { waitFor: '#page-content', ready: noSkeletons });
	await shot('site-admin', '/admin/search.php', { waitFor: '#page-content', ready: noSkeletons });
	await shot('plugins', '/admin/plugins.php', { waitFor: '#page-content', ready: noSkeletons });
	await shot('users', '/admin/user.php', { waitFor: '#page-content', ready: noSkeletons });

	await browser.close();
	log('screenshots captured');
};

main().catch(error => {
	console.error(`[screenshots] failed: ${error.message}`);
	process.exitCode = 1;
});
