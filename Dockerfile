ARG FRANKENPHP_VERSION=1-php8.3
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} as runtime

# WORKDIR /app

FROM runtime as dependencies

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends --purge \
		unzip \
	&& rm -rf \
		/var/lib/apt/lists/* \
		/var/lib/dpkg/info/* \
		/var/lib/dpkg/status-old

FROM dependencies as dev-environment

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

FROM dependencies as compilation

RUN rm -rf /app

COPY ./config /app/config
COPY ./libs /app/libs
COPY ./public /app/public
COPY composer.* /app

RUN COMPOSER_ALLOW_SUPERUSER=1 /usr/bin/composer install \
	--working-dir=/app \
	--prefer-dist \
	--classmap-authoritative \
	--no-dev

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-alpine as production

LABEL org.opencontainers.image.source=https://github.com/iyaki/simple-newsletter

COPY .php/php.ini $PHP_INI_DIR/php.ini
COPY .php/production.ini $PHP_INI_DIR/conf.d/prod.ini

RUN rm -rf /app

COPY --from=compilation /app /app
