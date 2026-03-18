# Doofinder for PrestaShop

![Release](https://img.shields.io/github/v/release/doofinder/doofinder-prestashop?style=flat-square) 
![PrestaShop](https://img.shields.io/badge/PrestaShop-1.5%20--%209.x-blue?style=flat-square) 
![PHP](https://img.shields.io/badge/PHP-%3E%3D%205.4-777bb4?style=flat-square) 
![License](https://img.shields.io/github/license/doofinder/doofinder-prestashop?style=flat-square)

**Transform your PrestaShop search into a conversion machine.** Join 10,000+ merchants using AI-powered search to increase sales and improve customer experience.

![Doofinder in Action]()

[🚀 Get Started for Free](https://www.doofinder.com/en/solutions/prestashop) | [🖥️ Live Demo](https://prestashop.doofinder.com/en/) | [📖 Full Documentation](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop)

---

## Why Doofinder?

Doofinder turns your basic search bar into an advanced discovery engine. Using AI-powered searchandising and recommendations, we drive measurable gains in conversion and product discovery.

### Key Features

* **AI Smart Search** — Understands intent and handles typos or synonyms effortlessly.
* **Searchandising** — Boost, hide, or pin products to run targeted campaigns.
* **Personalized Recommendations** — Intelligent cross-selling based on real customer behavior.
* **Visual Search** — Let your shoppers find products using images.
* **Auto-Indexing** — Your catalog stays in sync automatically as you scale.
* **AI Assistant** — Conversational search and support where available.

## Requirements

- **PHP:** Minimum 5.4; tested up to 8.4.
- **PrestaShop:** From 1.5.0.17 to latest (1.6, 1.7, 8.x, 9.x).

For system requirements by version, see [PrestaShop 1.7](https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/), [PrestaShop 8](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/), and [PrestaShop 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/).

---

## 🛠 Installation & Quick Start

### For Merchants
1.  **Download:** Get the [latest release zip](https://github.com/doofinder/doofinder-prestashop/releases).
2.  **Upload:** Go to your PrestaShop Back Office → **Modules** → **Module Manager** and click **Upload a module**.
3.  **Configure:** Follow our [Step-by-Step Installation Guide](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop).

### For Developers (Composer)
```bash
composer require doofinder/doofinder-prestashop
```

---

## 👨‍💻 Development & Maintainer Guide

This repository is optimized for local development using a **Makefile** and **Docker**.

> [!NOTE]
> `make doofinder-configure` generates the plugin files from the `templates/` directory (using `.env`) and runs `make dump-autoload` to regenerate the Composer autoloader. Many other targets depend on it, so running those targets keeps generated files in sync.

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

## Compatibility Matrix

We test against a wide range of PrestaShop and PHP combinations to ensure stability.Example combinations from [PrestaShop Docker Hub](https://hub.docker.com/r/prestashop/prestashop/tags):

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

---

## Support & Contributing

* **Need Help?** Visit our [Support Portal](https://support.doofinder.com/).
* **Found a Bug?** Please [contact Doofinder Support](https://support.doofinder.com/pages/contact-us) from the Doofinder website.
* **Want to help?** PRs are welcome!

**If you find this plugin useful, please give us a ⭐ to support the project!**
