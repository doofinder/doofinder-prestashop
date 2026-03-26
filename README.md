# Doofinder for PrestaShop

![Release](https://img.shields.io/github/v/release/doofinder/doofinder-prestashop?style=flat-square)
![PrestaShop](https://img.shields.io/badge/PrestaShop-1.5%20--%209.x-blue?style=flat-square) 
![PHP](https://img.shields.io/badge/PHP-%3E%3D%205.4-777bb4?style=flat-square)
![License](https://img.shields.io/github/license/doofinder/doofinder-prestashop?style=flat-square)

**Transform your PrestaShop search into a conversion machine.** Join 10,000+ merchants using AI-powered search to increase sales and improve customer experience.

![Doofinder in Action](https://github.com/user-attachments/assets/cac4ec30-02e4-4280-8ba4-8a738ab823f1)

[🚀 Get Started for Free](https://www.doofinder.com/en/solutions/prestashop) | [🖥️ Live Demo](https://prestashop.doofinder.com/en/) | [📖 Full Documentation](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop)

---

## Why Doofinder?

Doofinder turns your basic search bar into an advanced discovery engine. Using AI-powered searchandising and recommendations, we drive measurable gains in conversion and product discovery.

### Key Features

* **AI Assistant** — A smart shopping guide that helps customers find products through natural conversation.
* **AI Smart Search** — Understands intent and handles typos or synonyms effortlessly.
* **Searchandising** — Boost, hide, or pin products to run targeted campaigns.
* **Personalized Recommendations** — Intelligent cross-selling based on real customer behavior.
* **Visual Search** — Let your shoppers find products using images.
* **Auto-Indexing** — Your catalog stays in sync automatically as you scale.

---

## 🛠 Installation & Quick Start

**From GitHub (manual zip)**  
1. Download the [latest release zip](https://github.com/doofinder/doofinder-prestashop/releases).  
2. In your Back Office go to **Modules** → **Module Manager** → **Upload a module** and select the zip.

**From PrestaShop Addons**  
Install [Doofinder from PrestaShop Addons](https://addons.prestashop.com/en/search-filters/30818-doofinder-search-discovery.html) via the marketplace in your Back Office or the steps Addons gives you after download.

**Then**  
Complete setup using our [step-by-step installation guide](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop).

**Requirements**

- **PHP:** Minimum 5.4; tested up to 8.4.
- **PrestaShop:** From 1.5.0.17 to latest (1.6, 1.7, 8.x, 9.x).

For system requirements by version, see [PrestaShop 1.7](https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/), [PrestaShop 8](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/), and [PrestaShop 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/).

---

## 👨‍💻 Development & Maintainer Guide

This repository is optimized for local development using a **Makefile** and **Docker**.

**`.env`** sits at the repo root and powers both your **Docker** stack and the **generated module files** (what `doofinder-configure` pulls from `templates/`). It ships with sensible defaults—skim it, adjust shop URL, PrestaShop tag, and plugin version, then `make init`. Optional overrides go in **`.env.local`**, which loads on top of `.env`.

> [!NOTE]
> `make doofinder-configure` generates the plugin files from the `templates/` directory (using `.env`) and runs `make dump-autoload` to regenerate the Composer autoloader. Many other targets depend on it, so running those targets keeps generated files in sync.

### Environment and shop access

The root **`.env`** lists all variables with comments. For the **dev stack**, these are the ones you usually touch first:

| Variable | Role |
| -------- | ---- |
| `BASE_URL` | Shop hostname as seen by Docker (no `https://`). |
| `PRESTASHOP_DOCKER_TAG` | PrestaShop image/version used by the stack. |
| `MYSQL_*` | Database for the local shop. |
| `PS_*` | Installer options (language, country, domain, SSL, etc.). |
| `PS_ADMIN_EMAIL` / `PS_ADMIN_PASSWORD` | Back-office login after install. |
| `PS_FOLDER_ADMIN` | URL segment for the admin (see **Default access** below). |

**Default access (Docker dev stack):** After **`make init`**, open the shop using the host ports from **`docker-compose.yml`** (stock mapping: **9011** → HTTP, **4011** → HTTPS on the container). With the default **`BASE_URL=localhost`** and **`PS_FOLDER_ADMIN=4dm1n`** from `.env`, typical URLs are:

| | URL |
| -- | -- |
| Storefront (HTTP) | `http://localhost:9011/` |
| Storefront (HTTPS) | `https://localhost:4011/` |
| Back office (HTTP) | `http://localhost:9011/4dm1n` |
| Back office (HTTPS) | `https://localhost:4011/4dm1n` |

Back-office login is **`PS_ADMIN_EMAIL`** / **`PS_ADMIN_PASSWORD`** in `.env` (stock file uses `test@example.com` / `admin123`—change these for anything beyond local-only use). **`make init`** prints usable links; if you change ports or `BASE_URL`, adjust accordingly.

**Use cases:**

- **First-time setup:** Run `make init` once to build images, install PrestaShop, and start containers.
- **Start / stop the stack:** `make start`, `make stop`.
- **Install or upgrade the Doofinder module:** `make doofinder-upgrade`.
- **Uninstall the module:** `make doofinder-uninstall`.
- **Reinstall the module:** `make doofinder-reinstall`.
- **DB snapshot:** `make db-backup` (optionally `make db-backup prefix=_name`). Restore with `make db-restore file=backup.sql.gz`.
- **Clear cache:** `make cache-flush`.
- **Shell in the web container:** `make dev-console`.
- **Start from scratch:** Run `make clean` to drop Docker volumes and `./html`; type `DELETE` when prompted, then run `make init` for a fresh PrestaShop.
- **Debug with Xdebug:** The stack already enables Xdebug in Docker. Set `XDEBUG_HOST` and `XDEBUG_KEY` in `.env` or `.env.local` (e.g. `host.docker.internal` on Docker Desktop, your host IP on Linux), use the same key in your IDE, listen for connections, and browse the shop.

---

## Compatibility Matrix

We test against a wide range of PrestaShop and PHP combinations to ensure stability.
Example combinations from [PrestaShop Docker Hub](https://hub.docker.com/r/prestashop/prestashop/tags):

| PrestaShop | PHP                     |
| ---------- | ----------------------- |
| 8.2.1      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.1.7      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.0.5      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 1.7.8.9    | 7.4, 7.3, 7.2, 7.1      |
| 1.6        | 7.2, 7.1, 7.0, 5.6      |
| 1.5[^ps15] | 7.2, 7.1, 7.0, 5.6, 5.5 |

[^ps15]: PrestaShop 1.5: patched for auto installation (see Dockerfile). Use MySQL 5.5 and without SSL.

Set `PRESTASHOP_DOCKER_TAG` in the `.env` file and (if needed) PHP version in your Docker build args when using these combinations.

---

## Support & Contributing

* **Need Help?** Visit our [Support Portal](https://support.doofinder.com/).
* **Found a Bug?** Please [contact Doofinder Support](https://support.doofinder.com/pages/contact-us) from the Doofinder website.
* **Want to help?** PRs are welcome!

**If you find this plugin useful, please give us a ⭐ to support the project!**

## Try Doofinder / Learn more

Ready to improve your store search? [Get started with Doofinder for PrestaShop](https://www.doofinder.com/en/solutions/prestashop).
