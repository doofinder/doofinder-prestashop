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

[Try the demo](https://prestashop.doofinder.com/en/).

## Requirements

- **PHP:** Minimum 5.4; tested up to 8.4.
- **PrestaShop:** From 1.5.0.17 to latest (1.6, 1.7, 8.x, 9.x).

For system requirements by version, see [PrestaShop 1.7](https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/), [PrestaShop 8](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/), and [PrestaShop 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/).

## Quick start / Installation

Download the [latest release](https://github.com/doofinder/doofinder-prestashop/releases) or install from [PrestaShop Addons](https://addons.prestashop.com/en/search-filters/30818-doofinder-search-discovery.html). Upload the module in your PrestaShop Back Office (Modules → Module Manager → Upload module), then configure it following the [Doofinder PrestaShop installation guide](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop).

## Development / Contributing

This project relies on the Makefile for local setup and common tasks. Ensure a `.env` file is present in the repo root.

> **Note:** `make doofinder-configure` generates the plugin files from the `templates/` directory (using `.env`) and runs `make dump-autoload` to regenerate the Composer autoloader. Many other targets depend on it, so running those targets keeps generated files in sync.

**Use cases:**

- **First-time setup:** Run `make init` once to build images, install PrestaShop, and start containers.
- **Start / stop the stack:** `make start`, `make stop`.
- **Install or upgrade the Doofinder module:** `make doofinder-upgrade`.
- **Uninstall the module:** `make doofinder-uninstall`.
- **Reinstall the module:** `make doofinder-reinstall`.
- **Bump plugin version:** Update `PLUGIN_VERSION` in `.env`, then run `make doofinder-upgrade`.
- **DB snapshot:** `make db-backup` (optionally `make db-backup prefix=_name`). Restore with `make db-restore file=backup.sql.gz`.
- **Clear cache:** `make cache-flush`.
- **Shell in the web container:** `make dev-console`.

## Support

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
