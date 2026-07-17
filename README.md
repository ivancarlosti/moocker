# Moocker

A Docker-based Moodle training system `v5.2.1` on PHP `8.3` with Apache.

<!-- buttons -->
[![Stars](https://img.shields.io/github/stars/ivancarlosti/moocker?label=⭐%20Stars&color=gold&style=flat)](https://github.com/ivancarlosti/moocker/stargazers)
[![Watchers](https://img.shields.io/github/watchers/ivancarlosti/moocker?label=Watchers&style=flat&color=red)](https://github.com/sponsors/ivancarlosti)
[![Forks](https://img.shields.io/github/forks/ivancarlosti/moocker?label=Forks&style=flat&color=ff69b4)](https://github.com/sponsors/ivancarlosti)
[![Downloads](https://img.shields.io/github/downloads/ivancarlosti/moocker/total?label=Downloads&color=success)](https://github.com/ivancarlosti/moocker/releases)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/ivancarlosti/moocker?label=Activity)](https://github.com/ivancarlosti/moocker/pulse)
[![GitHub Issues](https://img.shields.io/github/issues/ivancarlosti/moocker?label=Issues&color=orange)](https://github.com/ivancarlosti/moocker/issues)  
[![License](https://img.shields.io/github/license/ivancarlosti/moocker?label=License)](LICENSE)
[![GitHub last commit](https://img.shields.io/github/last-commit/ivancarlosti/moocker?label=Last%20Commit)](https://github.com/ivancarlosti/moocker/commits)
[![Security](https://img.shields.io/badge/Security-View%20Here-purple)](https://github.com/ivancarlosti/moocker/security)
[![Code of Conduct](https://img.shields.io/badge/Code%20of%20Conduct-2.1-4baaaa)](https://github.com/ivancarlosti/moocker?tab=coc-ov-file)
<!-- endbuttons -->

## Overview

This project provides a production-ready Docker image for Moodle with the following features:

- **PHP 8.3** with Apache and Moodle-optimized extensions (`mysqli`, `zip`, `gd`, `intl`, `soap`, `opcache`, `exif`)
- **OPcache** pre-configured with Moodle-recommended values
- **PHP limits** tuned: `memory_limit=512M`, `max_execution_time=300`, `post_max_size=50M`, `upload_max_filesize=50M`, `max_input_vars=5000`
- **Automatic entrypoint** that generates `config.php` from environment variables, purges stale caches, runs pending upgrades via CLI, and starts Apache
- **Automatic SSL Proxy detection**: if `MOODLE_URL` uses `https://`, `sslproxy` is enabled automatically, preventing redirect loops behind reverse proxies
- **External database support**: the application expects an accessible MariaDB instance (local or remote), with no bundled database service in compose

> The image is automatically published via GitHub Actions to `ghcr.io/ivancarlosti/moocker:latest` whenever a new stable Moodle release is available.

## Prerequisites

- **Docker** and **Docker Compose** v2 installed
- An accessible **MariaDB** database (can be local, remote, or in a separate container)

## How to Run Locally

All required files are in the `docker` directory.

### 1. Prepare the Database

On your MariaDB server, create the database with the proper encoding:

```sql
CREATE DATABASE moodledb DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Environment Variables

Navigate to the `docker` directory and edit the `.env` file to match your environment:

```bash
cd docker
```

| Variable | Default | Description |
|---|---|---|
| `MOODLE_PORT` | `8080` | Local port to expose Moodle |
| `MOODLE_URL` | `http://localhost:8080` | Public URL of the instance |
| `MOODLE_SSLPROXY` | `true` | Forces SSL proxy mode (auto-detected if `MOODLE_URL` uses `https://`) |
| `DB_HOST` | `host.docker.internal` | MariaDB host |
| `DB_NAME` | `moodledb` | Database name |
| `DB_USER` | `moodleuser` | Database user |
| `DB_PASSWORD` | `moodlepass` | Database password |

> **`host.docker.internal`** automatically resolves to the host machine. If MariaDB is running directly on your machine (outside Docker), keep this value. For a database on another server, use the corresponding IP or domain.

### 3. Start the Container

```bash
docker compose up -d
```

### 4. Access Moodle

Open `http://localhost:8080` in your browser. Installation is fully automatic — the entrypoint generates `config.php` with the `.env` credentials and runs any pending upgrades. No web-based setup is required.

## Using with a Reverse Proxy (Nginx, Traefik, etc.)

To expose Moodle publicly behind a reverse proxy with HTTPS:

1. **Configure `.env`**:
   ```env
   MOODLE_URL=https://mymoodle.example.com
   ```
   The entrypoint automatically detects the `https://` scheme and enables `sslproxy=true`. To disable it, explicitly set `MOODLE_SSLPROXY=false`.

2. **Secure Port Binding**:
   The `docker-compose.yml` already binds the service only to `127.0.0.1:${MOODLE_PORT}:80`. Public access is only possible through the reverse proxy.

3. **Example Nginx configuration**:
   ```nginx
   server {
       listen 80;
       server_name mymoodle.example.com;

       location / {
           proxy_pass http://127.0.0.1:8080;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

4. **SSL Certificates**:
   With HTTPS on the proxy, `sslproxy` is enabled automatically. There is no longer any need to manually edit `config.php` — the entrypoint injects `$_SERVER['HTTPS'] = 'on'` when `sslproxy` is enabled.

## Complete Environment Variables

All variables supported by the container:

| Variable | Required | Default | Description |
|---|---|---|---|
| `MOODLE_DATABASE_TYPE` | No | `mariadb` | Database type |
| `MOODLE_DATABASE_HOST` | Yes | `db` | Database host |
| `MOODLE_DATABASE_NAME` | Yes | `moodle` | Database name |
| `MOODLE_DATABASE_USER` | Yes | `moodleuser` | Database user |
| `MOODLE_DATABASE_PASSWORD` | Yes | `moodlepass` | Database password |
| `MOODLE_URL` | No | `http://localhost:8080` | Public Moodle URL |
| `MOODLE_SSLPROXY` | No | auto | Forces `sslproxy` (`true`/`false`). If unset, auto-detected from the URL scheme |

## Project Structure

```
moocker/
├── Dockerfile                # Image build (PHP 8.3 + Moodle v5.2.1 + Apache)
├── moodle-entrypoint.sh      # Auto-configuration entrypoint script
├── manifest.json             # Project metadata and versioning
├── LICENSE                   # MIT License
├── README.md                 # This file
├── docker/
│   ├── .env                  # Environment variables for Docker Compose
│   ├── docker-compose.yml    # Moodle container orchestration
│   └── moodledata/           # Mounted volume for Moodle data
└── .github/                  # CI/CD workflows
```

<!-- footer -->
---

## 🧑‍💻 Consulting and technical support
* For personal support and queries, please submit a new issue to have it addressed.
* For commercial related questions, please [**contact me**][ivancarlos] for consulting costs.

[cc]: https://docs.github.com/en/communities/setting-up-your-project-for-healthy-contributions/adding-a-code-of-conduct-to-your-project
[contributing]: https://docs.github.com/en/articles/setting-guidelines-for-repository-contributors
[security]: https://docs.github.com/en/code-security/getting-started/adding-a-security-policy-to-your-repository
[support]: https://docs.github.com/en/articles/adding-support-resources-to-your-project
[it]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/configuring-issue-templates-for-your-repository#configuring-the-template-chooser
[prt]: https://docs.github.com/en/communities/using-templates-to-encourage-useful-issues-and-pull-requests/creating-a-pull-request-template-for-your-repository
[funding]: https://docs.github.com/en/articles/displaying-a-sponsor-button-in-your-repository
[ivancarlos]: https://ivancarlos.me
