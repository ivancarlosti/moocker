#!/usr/bin/env bash
#
# Regenerates the screenshots in this folder.
#
# Builds the Moocker image from the repository Dockerfile, boots it next to a
# throwaway MariaDB 11.4 container, installs Moodle through the CLI, seeds demo
# courses/users and drives a headless Chrome through the pages referenced from
# the root README.
#
# Requirements: docker, node >= 20 and a Chrome/Chromium build.
#
# Usage:
#   ./generate.sh                                   # build + boot + seed + capture
#   MOOCKER_PORT=24430 ./generate.sh                # avoid a port clash
#   MOOCKER_BASE_URL=http://localhost:8080 ./generate.sh   # capture an existing site
#   MOOCKER_IMAGE=moocker:local ./generate.sh       # reuse an existing image
#
# When capturing an existing site, set MOOCKER_COURSE_ID to the demo course id.
set -euo pipefail

cd "$(dirname "$0")"
SCREENSHOT_DIR="$(pwd)"
REPO_ROOT="$(cd ../.. && pwd)"

trap 'status=$?; echo "[generate] failed at line ${LINENO} with status ${status}" >&2' ERR

IMAGE="${MOOCKER_IMAGE:-moocker:screenshots}"
DB_IMAGE="${MOOCKER_DB_IMAGE:-mariadb:11.4}"
PORT="${MOOCKER_PORT:-24430}"
BASE_URL="${MOOCKER_BASE_URL:-http://localhost:${PORT}}"
CONTAINER="${MOOCKER_CONTAINER:-moocker-screenshots}"
DB_CONTAINER="${MOOCKER_DB_CONTAINER:-moocker-screenshots-db}"
NETWORK="${MOOCKER_NETWORK:-moocker-screenshots-net}"
VOLUME="${MOOCKER_VOLUME:-moocker-screenshots-data}"
ADMIN_USER="${MOOCKER_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${MOOCKER_ADMIN_PASSWORD:-Moocker-Screenshots-2026!}"
ADMIN_EMAIL="${MOOCKER_ADMIN_EMAIL:-admin@example.com}"
COURSE_ID="${MOOCKER_COURSE_ID:-}"
CATEGORY_ID="${MOOCKER_CATEGORY_ID:-}"

STARTED_APP=0
STARTED_DB=0
STARTED_NETWORK=0
STARTED_VOLUME=0

cleanup() {
	if [ "${MOOCKER_KEEP:-0}" = "1" ]; then
		echo "[generate] MOOCKER_KEEP=1, leaving the containers in place"
		return 0
	fi
	if [ "$STARTED_APP" = "1" ]; then
		echo "[generate] stopping ${CONTAINER}"
		docker rm -f "$CONTAINER" >/dev/null 2>&1 || true
	fi
	if [ "$STARTED_DB" = "1" ]; then
		echo "[generate] stopping ${DB_CONTAINER}"
		docker rm -f "$DB_CONTAINER" >/dev/null 2>&1 || true
	fi
	if [ "$STARTED_NETWORK" = "1" ]; then
		docker network rm "$NETWORK" >/dev/null 2>&1 || true
	fi
	if [ "$STARTED_VOLUME" = "1" ]; then
		docker volume rm "$VOLUME" >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT INT TERM

if [ -z "${MOOCKER_BASE_URL:-}" ]; then
	if [ -z "${MOOCKER_IMAGE:-}" ]; then
		echo "[generate] building ${IMAGE} from ${REPO_ROOT}"
		docker build -t "$IMAGE" "$REPO_ROOT"
	fi

	echo "[generate] preparing a throwaway database and network"
	docker rm -f "$CONTAINER" "$DB_CONTAINER" >/dev/null 2>&1 || true
	docker network rm "$NETWORK" >/dev/null 2>&1 || true
	docker volume rm "$VOLUME" >/dev/null 2>&1 || true
	docker network create "$NETWORK" >/dev/null
	STARTED_NETWORK=1
	docker volume create "$VOLUME" >/dev/null
	STARTED_VOLUME=1

	echo "[generate] starting ${DB_IMAGE}"
	docker run -d --rm \
		--name "$DB_CONTAINER" \
		--network "$NETWORK" \
		-e MARIADB_ROOT_PASSWORD=root \
		-e MARIADB_DATABASE=moodle \
		-e MARIADB_USER=moodleuser \
		-e MARIADB_PASSWORD=moodlepass \
		"$DB_IMAGE" \
		--character-set-server=utf8mb4 \
		--collation-server=utf8mb4_unicode_ci >/dev/null
	STARTED_DB=1

	echo '[generate] waiting for the database to accept connections'
	attempt=1
	until docker exec "$DB_CONTAINER" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; do
		if [ "$attempt" -ge 90 ]; then
			echo '[generate] the database did not come up in time' >&2
			exit 1
		fi
		attempt=$((attempt + 1))
		sleep 2
	done

	echo "[generate] starting ${IMAGE} on ${BASE_URL}"
	docker run -d --rm \
		--name "$CONTAINER" \
		--network "$NETWORK" \
		-p "127.0.0.1:${PORT}:80" \
		-e MOODLE_DATABASE_HOST="$DB_CONTAINER" \
		-e MOODLE_DATABASE_NAME=moodle \
		-e MOODLE_DATABASE_USER=moodleuser \
		-e MOODLE_DATABASE_PASSWORD=moodlepass \
		-e MOODLE_URL="$BASE_URL" \
		-v "${VOLUME}:/var/www/moodledata" \
		"$IMAGE" >/dev/null
	STARTED_APP=1

	echo '[generate] waiting for Apache'
	attempt=1
	until [ "$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/login/index.php" || true)" != "000" ]; do
		if [ "$attempt" -ge 120 ]; then
			echo '[generate] the application did not come up in time' >&2
			docker logs "$CONTAINER" >&2 || true
			exit 1
		fi
		attempt=$((attempt + 1))
		sleep 2
	done

	if ! docker exec "$CONTAINER" php /var/www/html/admin/cli/cfg.php --name=version >/dev/null 2>&1; then
		echo '[generate] installing Moodle through the CLI'
		docker exec -u www-data "$CONTAINER" php /var/www/html/admin/cli/install_database.php \
			--agree-license \
			--lang=en \
			--adminuser="$ADMIN_USER" \
			--adminpass="$ADMIN_PASSWORD" \
			--adminemail="$ADMIN_EMAIL" \
			--fullname="Moocker Demo" \
			--shortname="Moocker"
	else
		echo '[generate] Moodle is already installed in this database'
	fi

	echo '[generate] seeding demo content'
	docker cp seed.php "$CONTAINER:/tmp/moocker-seed.php"
	SEED_OUTPUT=$(docker exec -u www-data "$CONTAINER" php /tmp/moocker-seed.php)
	printf '%s\n' "$SEED_OUTPUT"
	SEEDED_COURSE_ID=$(printf '%s\n' "$SEED_OUTPUT" | sed -n 's/^COURSE_ID=//p' | tail -n 1)
	if [ -n "$SEEDED_COURSE_ID" ]; then
		COURSE_ID="$SEEDED_COURSE_ID"
	fi
	SEEDED_CATEGORY_ID=$(printf '%s\n' "$SEED_OUTPUT" | sed -n 's/^CATEGORY_ID=//p' | tail -n 1)
	if [ -n "$SEEDED_CATEGORY_ID" ]; then
		CATEGORY_ID="$SEEDED_CATEGORY_ID"
	fi
else
	echo "[generate] using the instance already running at ${BASE_URL}"
	if [ -z "$COURSE_ID" ]; then
		echo '[generate] MOOCKER_COURSE_ID is not set; the course page capture will be skipped'
	fi
fi

if [ ! -d node_modules/playwright-core ]; then
	echo '[generate] installing browser automation dependencies'
	npm install --no-fund --no-audit
fi

BASE_URL="$BASE_URL" \
	DEMO_COURSE_ID="$COURSE_ID" \
	DEMO_CATEGORY_ID="$CATEGORY_ID" \
	ACCOUNT_LOGIN="$ADMIN_USER" \
	ACCOUNT_PASSWORD="$ADMIN_PASSWORD" \
	OUTPUT_DIR="$SCREENSHOT_DIR" \
	node capture.mjs

echo "[generate] done. Screenshots written to ${SCREENSHOT_DIR}"
