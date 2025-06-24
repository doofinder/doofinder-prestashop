ARG PRESTASHOP_DOCKER_TAG=latest

FROM prestashop/prestashop:${PRESTASHOP_DOCKER_TAG}

# For debian versions < 9.0 and >= 8.0
RUN echo "8\n$(cat /etc/debian_version)\n9"  | sort -cuV \
    && echo "deb http://archive.debian.org/debian/ jessie main\ndeb http://archive.debian.org/debian-security/ jessie/updates main" > /etc/apt/sources.list \
    || :

RUN echo "8\n$(cat /etc/debian_version)\n9" | sort -cuV && apt-get update || :

RUN echo "8\n$(cat /etc/debian_version)\n9" | sort -cuV &&  apt-get install --reinstall ca-certificates -y --force-yes || :

# Use development php.ini in versions >= 5.6
RUN echo "5.6\n${PHP_VERSION}" | sort -cV && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" || :

RUN echo 'memory_limit = -1' >> "$PHP_INI_DIR/conf.d/docker-php-ram-limit.ini"

# XDebug extension
RUN pecl channel-update pecl.php.net
# For PHP versions <= 8.0, use xdebug 3.1.5
RUN echo "8\n${PHP_VERSION}" | sort -cuV && pecl install xdebug || :
# For PHP versions < 8.0 and >= 7.0
RUN echo "7\n${PHP_VERSION}\n8" | sort -cuV && pecl install xdebug-3.1.5 || :
# For PHP versions >= 7.0
RUN echo "7\n${PHP_VERSION}" | sort -cuV  && docker-php-ext-enable xdebug || :

## Remove last line exec to end script and use in setup service
RUN head -n -1 /tmp/docker_run.sh > /tmp/docker_setup.sh
RUN chmod +x /tmp/docker_setup.sh

# Fix 1.5 installation
RUN echo "1.6\n${PS_VERSION}" | rm /tmp/data-ps/prestashop/install/fixtures/apple/data/order_history.xml || :
