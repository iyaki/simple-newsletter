FROM dunglas/frankenphp@sha256:2a9c6663f73ad1e401909634206bd25e13bed5b94d86eefa3b5ec64c985b4595 AS dependencies
# ^ digest of dunglas/frankenphp:1-php8.5 at audit time (2026-10-09); bump
#   deliberately, never by rebuilding.

COPY --from=composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/bin/composer
# ^ digest of library/composer:2 at audit time (2026-10-09)

RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends --purge \
		unzip \
	&& rm -rf \
		/var/lib/apt/lists/* \
		/var/lib/dpkg/info/* \
		/var/lib/dpkg/status-old \
	&& install-php-extensions intl

FROM dependencies AS dev-environment

RUN install-php-extensions xdebug \
	&& apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends --purge \
		bash-completion \
		openssh-client \
		# ssh \
		vim \
	&& rm -rf \
		/var/lib/apt/lists/* \
		/var/lib/dpkg/info/* \
		/var/lib/dpkg/status-old

FROM dependencies AS app-compilation

RUN rm -rf /app

COPY ./bin /app/bin
COPY ./config /app/config
COPY ./libs /app/libs
COPY ./public /app/public
COPY composer.* /app

RUN COMPOSER_ALLOW_SUPERUSER=1 /usr/bin/composer install \
	--working-dir=/app \
	--prefer-dist \
	--classmap-authoritative \
	--no-dev

FROM dunglas/frankenphp@sha256:7e70af992787717312ba98c7ab555ae050be8de0c506fd6aa93b850b36e22285 AS production
# ^ digest of dunglas/frankenphp:1-php8.5-alpine at audit time (2026-10-09)

LABEL org.opencontainers.image.source=https://github.com/iyaki/simple-newsletter

COPY .php/php.ini $PHP_INI_DIR/php.ini
COPY .php/production.ini $PHP_INI_DIR/conf.d/prod.ini

COPY .caddy/Caddyfile-prod /etc/caddy/Caddyfile

RUN rm -rf /app

COPY --from=app-compilation /app /app
