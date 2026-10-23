# Moocker screenshots

The images in this folder are referenced from the root `README.md`. They are
generated from a real Moodle instance, not mocked up.

## Regenerate

```bash
cd docs/screenshots
./generate.sh
```

The script:

1. builds `moocker:screenshots` from the repository `Dockerfile`
   (unless `MOOCKER_IMAGE` points at an existing image);
2. starts a throwaway `mariadb:11.4` container and a Moocker container on
   `http://localhost:24430`;
3. installs Moodle through `admin/cli/install_database.php`;
4. seeds a category, three courses, five learners, enrolments and named
   sections with `seed.php` (also disables the onboarding tours and enables
   the My courses page);
5. logs in with Playwright's request context (so the browser reuses the
   authenticated cookies) and writes the PNGs listed below;
6. removes the containers, network and volume.

Requirements: Docker, Node.js >= 20 and Google Chrome (or Chromium).

### Options

| Variable | Default | Purpose |
|----------|---------|---------|
| `MOOCKER_PORT` | `24430` | Port exposed on the host. |
| `MOOCKER_IMAGE` | `moocker:screenshots` | Reuse an existing image instead of building. |
| `MOOCKER_BASE_URL` | `http://localhost:<port>` | Capture an already running instance. |
| `MOOCKER_COURSE_ID` | detected | Course id used for `course.png` when capturing an existing instance. |
| `MOOCKER_CATEGORY_ID` | detected | Category id used for `course-management.png` when capturing an existing instance. |
| `MOOCKER_ADMIN_USER` / `MOOCKER_ADMIN_PASSWORD` | `admin` / `Moocker-Screenshots-2026!` | Login used for the captures. |
| `MOOCKER_DB_IMAGE` | `mariadb:11.4` | Database image. |
| `MOOCKER_KEEP` | `0` | Set to `1` to leave the containers, network and volume in place for debugging. |
| `PW_CHANNEL` | `chrome` | Playwright browser channel. |

## Files

| File | Page |
|------|------|
| `login.png` | `/login/index.php` |
| `dashboard.png` | `/my/` |
| `courses.png` | `/my/courses.php` |
| `course.png` | `/course/view.php?id=<demo course>` |
| `course-management.png` | `/course/management.php?categoryid=<demo category>` |
| `site-admin.png` | `/admin/search.php` |
| `plugins.png` | `/admin/plugins.php` |
| `users.png` | `/admin/user.php` |

`node_modules/` is ignored; everything else (including the PNGs) is committed.

See [`../../plans/2026-10-23-readme-screenshots.md`](../../plans/2026-10-23-readme-screenshots.md)
for the rationale and the validation checklist.
