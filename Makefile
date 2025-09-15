.PHONY: all cache-flush clean consistency db-backup db-restore dev-console doofinder-configure doofinder-reinstall doofinder-uninstall doofinder-upgrade init start stop

# Include environment variables from .env file
ifeq ("$(wildcard .env)","")
	$(error Please be sure a `.env` file is present in the root directory. You can make a copy of `.env.example`)
endif

include .env
export

docker_compose ?= docker compose
ifneq ("$(wildcard .env.local)","")
	include .env.local
	export
	docker_compose = docker compose --env-file .env --env-file .env.local
endif

docker_exec_web = $(docker_compose) exec -u www-data prestashop

# Default target: list available tasks
all:
	@echo "Before \`make init\` be sure to set up your environment with a proper \`.env\` file."
	@echo "Select a task defined in the Makefile:"
	@echo "  all, cache-flush, clean, consistency, db-backup, db-restore, dev-console,"
	@echo "  doofinder-configure, doofinder-reinstall, doofinder-uninstall,"
	@echo "  doofinder-upgrade, init, start, stop"

# Backup the MySQL database from the 'db' container and compress the output
db-backup:
	$(docker_compose) exec db /usr/bin/mysqldump -u root -p$(MYSQL_PASSWORD)  $(MYSQL_DATABASE) | gzip > backup_$(shell date +%Y%m%d%H%M%S)$(prefix).sql.gz

# Restore the MySQL database using a provided backup file (pass file=<backupfile> as argument)
db-restore:
	@[ -e "$(file)" ] || (echo "Error: 'file' variable not provided. Use file=<backupfile>" && exit 1)
	gunzip < $(file) | $(docker_compose) exec -T db /usr/bin/mysql -u root -p$(MYSQL_PASSWORD)  $(MYSQL_DATABASE)

# Configures extension static files
doofinder-configure:
	@envsubst < templates/src/Core/DoofinderConstants.php > src/Core/DoofinderConstants.php

# Enable the Doofinder module, upgrade PrestaShop, and clean the cache
doofinder-upgrade: doofinder-configure
	$(docker_exec_web) php bin/console prestashop:module install doofinder
	$(docker_exec_web) php bin/console prestashop:module enable doofinder
	$(docker_exec_web) php bin/console prestashop:module upgrade doofinder

# Disable the Doofinder module, upgrade PrestaShop, and clean the cache
doofinder-uninstall: doofinder-configure
	$(docker_exec_web) php bin/console prestashop:module disable doofinder
	$(docker_exec_web) php bin/console prestashop:module uninstall doofinder

# Reinstall Doofinder: disable then re-enable the module
doofinder-reinstall: doofinder-uninstall doofinder-upgrade

# Flush the PrestaShop cache
cache-flush:
	$(docker_exec_web) php bin/console cache:clear

# Build Docker images, install PrestaShop, and start containers
init: doofinder-configure
	$(docker_compose) pull --ignore-buildable
	$(docker_compose) build
	$(docker_compose) --profile setup run --rm setup
	@echo "Fixing permissions for the html directory"
	sudo chgrp -R ${GID} ./html
	sudo chmod -R g+sw ./html
	sudo rm -rf ./html/install
	@echo "Access backend at $(if $(PS_ENABLE_SSL,1), https, http)://$(PS_BASE_URL)/$(PS_FOLDER_ADMIN)"
	$(docker_compose) up -d

# Check code consitency for the Doofinder Feed module using PHP Code Sniffer
consistency:
	docker run -it --rm -ePHP_CS_FIXER_IGNORE_ENV=1 \
		-v$(shell pwd):/app -v/app/html -v/app/vendor \
		composer:lts sh -c \
		"composer install && \
		vendor/bin/php-cs-fixer fix --diff --using-cache=no"

dump-autoload:
	docker run -it --rm -u $(shell id -u):$(shell id -g) -v$(shell pwd):/app composer:lts sh -c "composer install --no-dev && composer dump-autoload -o --no-dev"

# Open an interactive shell in the web container as the 'application' user
dev-console:
	$(docker_exec_web) bash

# Start the PrestaShop Docker containers
start: doofinder-configure
	@echo "(PrestaShop) Starting"
	@$(docker_compose) up -d --force-recreate
	@echo "(PrestaShop) Started"

# Stop the PrestaShop Docker containers
stop:
	@echo "(PrestaShop) Stopping"
	@$(docker_compose) down
	@echo "(PrestaShop) Stopped"

clean:
	@echo "\033[33m⚠️ WARNING ⚠️\033[0m"
	@echo "This will permanently delete"
	@echo "  - All Docker volumes for this project"
	@echo "  - The entire ./html directory, including all Prestashop files"
	@echo -n "Type 'DELETE' to confirm removing all volumes and ./html directory: " && read ans && [ "$${ans}" = "DELETE" ]
	$(docker_compose) down -v
	sudo rm -rf ./html
