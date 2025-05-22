ARG PHP_VERSION=7.4
ARG PS_VERSION=1.7.8.6

FROM prestashop/prestashop:${PS_VERSION}-${PHP_VERSION}

# Use development php.ini
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN echo 'memory_limit = -1' >> "$PHP_INI_DIR/conf.d/docker-php-ram-limit.ini"

# XDebug extension
RUN pecl channel-update pecl.php.net
RUN echo -e "8.0\n${PHP_VERSION%-*}" | sort -cV && pecl install xdebug || pecl install xdebug-3.1.5
RUN docker-php-ext-enable xdebug
