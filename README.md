# doofinder-prestashop

Plugin that allows to configure the [Doofinder](https://www.doofinder.com) search service in a Prestashop 1.5 store with less effort than configuring it from scratch.

> [!IMPORTANT]
> If you experience any issue with the module, please [contact Doofinder Support](https://support.doofinder.com/pages/contact-us) from the Doofinder website.

## How to install and configure Doofinder

Refer to [Doofinder Support Documentation for Prestashop](https://support.doofinder.com/plugins/prestashop/installation-guide/installation-steps-prestashop) for the latest and up to date instructions.

## Module Compatibility

### PHP

The minimum php required for this module is php 5.4
The maximum php version tested is 8.4

### PrestaShop

From 1.5.0.17 to 9.0.0

For more compatibility details check the following documentation

- [Prestashop 1.x](https://devdocs.prestashop-project.org/1.7/basics/installation/system-requirements/) system requirements.
- [Prestashop 8](https://devdocs.prestashop-project.org/8/basics/installation/system-requirements/) system requirements.
- [Prestashop 9](https://devdocs.prestashop-project.org/9/basics/installation/system-requirements/) system requirements.

## Docker Environment

### Configure ngrok

In order to be able to create an account or login to an existing Doofinder account during the module initial setup, you will have to expose your local webserver to the internet (to receive a callback).

To do so, you can use, for example, the utility ngrok: https://dashboard.ngrok.com/get-started/setup

Once the external URL is created, simply set the `BASE_URL` environment variable (see [Environment Variables](#environment-variables)).

So, when the installation process finished, instead of accessing to `https://localhost:4011` you will use your url, for example, `https://forcibly-ethical-apple.ngrok-free.app`).
Notice that you'll need to specify the 4011 port when executing ngrok.

### Environment variables

> [!TIP]
> You can create an `.env.local` file to override the environment variables defined in `.env` such as PrestaShop installation data to fit your needs.

For example, below is a base `.env.local` file:

```bash
#PrestaShop setup configuration data
BASE_URL=your-url.ngrok-free.app
PS_ENV=dev

```

The `Makefile` automatically overrides `.env` vars with the ones found in `.env.local`.

> [!IMPORTANT]
> The `Makefile` internally appends `--env-file .env --env-file .env.local` to `docker compose` command for properly configuring container environment. So take it into account when interacting directly with `docker compose`.

### Initial setup

You can set up a fresh PrestaShop installation using the provided `Makefile` target `init`. This command will:

- Pulls and build a PrestaShop docker image with xdebug extension and maybe other tweaks. This build is configurable using the environment variables `PHP_VERSION` and `PS_VERSION` environment variables.
- Starts the containers
- Runs the installer script thanks to `PS_INSTALL_AUTO` and the definded environment variables.

Finally, PrestaShop is installed and will be running at `https://BASE_URL`.

You can install the Doofinder module through the admin or execute `make doofinder-upgrade`.

The admin panel will be available at `https://BASE_URL/admin`. Admin credentials are defined in the `.env`, if you used the `env.example` would be:

- User: `test@example.com`
- Pass: `admin123`

> [!NOTE]
> Keep in mind that for versions prior to 1.7 PrestaShop will ask you to delete the `install` folder and rename the `admin` folder located in the `html` directory.
> For newer versions this is done automatically, and accessing `/admin` will redirect you to the correct url. If this no happens, simply check the admin folder name inside the `html` directory and use it to access to the Admin Dashboard.

## Xdebug ready to use

If you wish to debug your new PrestaShop installation, simply uncomment the `XDEBUG_CONFIG` and `XDEBUG_MODE` environment variables in `docker-compose.yml` configure your IDE accordingly and have fun!

## Uninstall the module

You can remove the Doofinder module using this straightforward method:

```sh
make doofinder-uninstall
```

## Test another versions of the module

Change your branch to the tag that you want inside package directory

```sh
make doofinder-upgrade
```

## Backup and Restore Database

During development, it is sometimes useful to create a data snapshot before performing an action.

- To create a database dump, use:
  ```sh
  make db-backup [prefix=_some_state]
  ```
- To restore a previous state, run:
  ```sh
  make db-restore file=backup_file.sql.gz
  ```

## Test other PrestaShop versions

You can test different Prestashop versions along with different PHP versions. These are the latest combinations available gathered from [PrestaShop Docker Hub](https://hub.docker.com/r/prestashop/prestashop/tags)

| PrestaShop | PHP                     |
| ---------- | ----------------------- |
| 8.2.1      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.1.7      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 8.0.5      | 8.1, 8.0, 7.4, 7.3, 7.2 |
| 1.7.8.9    | 7.4, 7.3, 7.2, 7.1      |
| 1.6        | 7.2, 7.1, 7.0, 5.6      |
| 1.5[^ps15] | 7.2, 7.1, 7.0, 5.6, 5.5 |

[^ps15]: Prestashop 1.5: This version is patched to allow auto installation (See Dockerfile). MySQL version must be 5.5. Must be used without SSL.
