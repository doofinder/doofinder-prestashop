# Doofinder for PrestaShop

Official Doofinder integration for PrestaShop. Replaces default search with AI-powered site search, recommendations, and searchandising—so your store performs like the 10,000+ shops already using Doofinder.

## About Doofinder

Doofinder helps stores grow by turning their search into an advanced search engine. AI-powered search, searchandising, and recommendations drive measurable gains in conversion and discovery. Try it free—no commitment.

## Features

- **AI Smart Search** — Understands intent and delivers relevant results even with typos, synonyms, or vague terms.
- **Searchandising** — Boost, hide, or pin products and run campaigns directly in search results.
- **Personalized Recommendations** — Cross-sell and upsell based on customer behavior.
- **Auto-Indexing** — Keeps your catalog in sync with minimal setup as you scale.
- **Visual & Image Search** — Shoppers can search by image; AI improves findability.
- **AI Assistant** — Conversational search and support where available.

## Requirements

- **PHP:** Minimum 5.4; tested up to 8.4.
- **PrestaShop:** From 1.5.0.17 to latest (1.6, 1.7, 8.x, 9.x).

For system requirements by version, see [PrestaShop 1.7](https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/), [PrestaShop 8](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/), and [PrestaShop 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/).

## Quick start / Installation

1. Clone the repo.
2. Copy `.env.example` to `.env` and set at least `BASE_URL` (and any Doofinder URLs if needed).
3. Run `make init` to build, install PrestaShop, and start containers.
4. Open your shop at `https://BASE_URL` (e.g. `https://localhost:4011`). Install or enable the Doofinder module from the admin or run `make doofinder-upgrade`.

Admin panel: `https://BASE_URL/PS_FOLDER_ADMIN` (default credentials in `.env.example`: `test@example.com` / `admin123`).

> [!NOTE]
> For PrestaShop versions prior to 1.7 you may need to delete the `install` folder and rename the `admin` folder in `html`. For newer versions this is handled automatically using `PS_FOLDER_ADMIN` (default `/4dm1n`).

## Environment variables

All supported variables are documented in `.env.example`. Copy it to `.env` and adjust. Main groups:

| Purpose | Variables |
|--------|-----------|
| Plugin & Doofinder services | `PLUGIN_VERSION`, `DOOMANAGER_REGION_URL`, `DOOPLUGINS_REGION_URL`, `DOOPHOENIX_REGION_URL`, `CONFIG_REGION_URL` |
| Store | `BASE_URL` |
| Database (Docker) | `MYSQL_VERSION`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD` |
| PrestaShop installer | `PRESTASHOP_DOCKER_TAG`, `PS_DB_PREFIX`, `PS_LANGUAGE`, `PS_COUNTRY`, `PS_FOLDER_ADMIN`, `PS_ENABLE_SSL`, `PS_ADMIN_EMAIL`, `PS_ADMIN_PASSWORD` |

You can override any of these in `.env.local`; the Makefile loads `.env` then `.env.local`. Use production URLs only in committed files.

## Make targets

| Target | Description |
|--------|-------------|
| `make all` | Show this list of targets. |
| `make init` | Build images, run PrestaShop installer, start containers. Run once for a fresh install. |
| `make start` | Start Docker containers (runs `doofinder-configure` first). |
| `make stop` | Stop Docker containers. |
| `make doofinder-configure` | Substitute env vars into `composer.json`, `src/Core/DoofinderConstants.php`, and `doofinder.php`; run dump-autoload. |
| `make doofinder-upgrade` | Install/enable/upgrade the Doofinder module. |
| `make doofinder-uninstall` | Disable and uninstall the Doofinder module. |
| `make doofinder-reinstall` | Uninstall then reinstall the module. |
| `make cache-flush` | Clear PrestaShop cache. |
| `make db-backup [prefix=_name]` | Dump MySQL DB to a timestamped `.sql.gz` file. |
| `make db-restore file=backup.sql.gz` | Restore DB from a backup file. |
| `make consistency` | Run PHP CS Fixer for code style. |
| `make dev-console` | Open a shell in the web container. |
| `make dump-autoload` | Regenerate Composer autoload (run after adding classes). |
| `make clean` | Remove Docker volumes and `./html` (destructive; prompts for confirmation). |

## Uninstall / Upgrade

- **Uninstall the module:** `make doofinder-uninstall`
- **Upgrade or reinstall:** Edit `PLUGIN_VERSION` in `.env`, run `make doofinder-configure`, then `make doofinder-upgrade`. You must also set `$this->version` in `doofinder.php` to the same value (PrestaShop requires it hardcoded).

## Backup & restore

- **Backup:** `make db-backup` or `make db-backup prefix=_some_state`
- **Restore:** `make db-restore file=backup_YYYYMMDDHHMMSS.sql.gz`

## Support / Contributing

For help, feature requests, or bugs: [Doofinder Support](https://support.doofinder.com/). When reporting issues, include your PrestaShop and plugin version.

Installation and configuration details: [Doofinder Support – PrestaShop installation guide](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop).

## Try Doofinder / Learn more

Ready to improve your store search? [Get started with Doofinder for PrestaShop](https://www.doofinder.com/en/solutions/prestashop).

---

## Test other PrestaShop versions

You can test different PrestaShop and PHP versions. Example combinations from [PrestaShop Docker Hub](https://hub.docker.com/r/prestashop/prestashop/tags):

| PrestaShop | PHP                     |
| ---------- | ----------------------- |
| 8.2.1      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.1.7      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.0.5      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 1.7.8.9    | 7.4, 7.3, 7.2, 7.1      |
| 1.6        | 7.2, 7.1, 7.0, 5.6      |
| 1.5[^ps15] | 7.2, 7.1, 7.0, 5.6, 5.5 |

[^ps15]: PrestaShop 1.5: patched for auto installation (see Dockerfile). Use MySQL 5.5 and without SSL.

Set `PRESTASHOP_DOCKER_TAG` and (if needed) PHP version in your Docker build args when using these combinations.
