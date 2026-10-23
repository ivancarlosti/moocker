# README Screenshots — Moocker

- **Date:** 2026-10-23
- **Scope:** document the Moocker UI in `README.md` with screenshots captured
  from a real instance, stored in `docs/screenshots/`.
- **Status:** Implemented and validated in this change set.

---

## 1. Goal

The README described the image but showed nothing of the running Moodle site.
This change adds:

- a **Screenshots** section to `README.md` with a two-column image table;
- a hero image under the project buttons;
- the PNGs themselves, hosted in the repository at `docs/screenshots/`;
- reproducible capture tooling next to the images.

GitHub renders relative image paths directly, so `docs/screenshots/*.png` is
the hosting location. No GitHub Pages workflow is required.

## 2. Captured pages

| File | Moodle page |
|------|-------------|
| `login.png` | `/login/index.php` |
| `dashboard.png` | `/my/` |
| `courses.png` | `/my/courses.php` (My courses) |
| `course.png` | `/course/view.php?id=<demo course>` |
| `course-management.png` | `/course/management.php?categoryid=<demo category>` |
| `site-admin.png` | `/admin/search.php` (Site administration) |
| `plugins.png` | `/admin/plugins.php` (Plugins overview) |
| `users.png` | `/admin/user.php` (Browse list of users) |

All captures use a 1440×900 viewport, a seeded instance, and the built-in
Boost theme.

## 3. Pipeline

`docs/screenshots/generate.sh`:

1. builds the image from the repository `Dockerfile`
   (`MOOCKER_IMAGE` can point at an existing image, `MOOCKER_BASE_URL` at an
   already running instance);
2. starts a throwaway **MariaDB 11.4** container (Moodle 5.3 minimum version);
3. starts the Moocker container on a configurable port;
4. waits for Apache, then installs Moodle through
   `admin/cli/install_database.php` with a known admin account;
5. runs `seed.php` inside the container to create a demo category, three
   courses, five users, enrolments and named sections, enables the My courses
   page and disables the first-run onboarding tours;
6. drives Chrome through `capture.mjs` (Playwright) and writes the PNGs;
7. removes the containers, network and moodledata volume.

`capture.mjs` uses `playwright-core` with the system Chrome (`channel:
'chrome'`) so no browser download is needed. Set `PW_CHANNEL` to use a
different channel.

Login is performed through the Playwright request context rather than by
filling the form in the browser. Moodle validates a new session with a
`login/index.php?testsession=<id>` redirect, which is reliable over the HTTP
client; the browser then reuses the resulting cookies. This also avoids
headless-Chrome cookie races that intermittently showed "Unable to log in".

## 4. Usage

```bash
cd docs/screenshots
./generate.sh                    # build + boot + seed + capture
MOOCKER_PORT=24430 ./generate.sh # avoid a port clash
MOOCKER_BASE_URL=http://localhost:8080 ./generate.sh  # capture an existing site
MOOCKER_IMAGE=moocker:local ./generate.sh            # reuse a local build
```

The script requires Docker, Node.js >= 20 and Google Chrome/Chromium. The
generated PNGs are committed on purpose; `node_modules/` and temporary demo
assets are ignored.

## 5. README changes

- Hero screenshot (`docs/screenshots/dashboard.png`, 880 px wide) after the
  badges block.
- `## Screenshots` section after the overview, with a table of all eight
  images and their alt text.
- `docs/screenshots/` and `plans/` added to the project-structure tree.

## 6. Validation

The final run of `./generate.sh` completed end-to-end on a clean database:

- all eight PNGs were regenerated at 1440×900 and are non-empty;
- the dashboard shows the three seeded course cards (not skeleton loaders);
- the course page shows the seeded labels, page and forum, with named sections;
- the course management page shows the `Moocker Demo` category and its courses;
- every `docs/screenshots/*.png` path referenced from `README.md` resolves;
- cleanup left no `moocker-screenshots*` containers, network or volume behind.

## 7. Notes

- Moodle 5.3 is the first branch targeting PHP 8.5; see
  [`2026-10-23-php-85-upgrade.md`](2026-10-23-php-85-upgrade.md).
- The capture pipeline needed the `FallbackResource /r.php` Apache fix from the
  Dockerfile; without it Moodle's `/core/esm/...` assets 404 and blocks stay on
  skeleton placeholders.
- The capture pipeline is intentionally **not** part of CI: it needs a browser,
  a database and several minutes. Regenerate manually when the UI changes.
