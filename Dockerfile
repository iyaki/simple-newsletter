ARG FRANKENPHP_VERSION=1
ARG PHP_VERSION=php8.3
FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-${PHP_VERSION} AS runtime

# WORKDIR /app

FROM runtime AS dependencies

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends --purge \
		unzip \
	&& rm -rf \
		/var/lib/apt/lists/* \
		/var/lib/dpkg/info/* \
		/var/lib/dpkg/status-old

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

COPY ./config /app/config
COPY ./libs /app/libs
COPY ./public /app/public
COPY composer.* /app

RUN COMPOSER_ALLOW_SUPERUSER=1 /usr/bin/composer install \
	--working-dir=/app \
	--prefer-dist \
	--classmap-authoritative \
	--no-dev

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-builder-${PHP_VERSION}-alpine AS production-builder

# Copy xcaddy in the builder image
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

# CGO must be enabled to build FrankenPHP
ENV CGO_ENABLED=1 XCADDY_SETCAP=1 XCADDY_GO_BUILD_FLAGS="-ldflags '-w -s'"
RUN xcaddy build \
	--output /usr/local/bin/frankenphp \
	--with github.com/dunglas/frankenphp=./ \
	--with github.com/dunglas/frankenphp/caddy=./caddy/ \
	--with github.com/dunglas/caddy-cbrotli \
	# Para que cloudflare funcione adecuadamente es importante que el SSL/TLS del dominio este configurado como Full (strict)
	--with github.com/caddy-dns/cloudflare

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-${PHP_VERSION}-alpine AS production

LABEL org.opencontainers.image.source=https://github.com/iyaki/simple-newsletter

COPY --from=production-builder /usr/local/bin/frankenphp /usr/local/bin/frankenphp

COPY .php/php.ini $PHP_INI_DIR/php.ini
COPY .php/production.ini $PHP_INI_DIR/conf.d/prod.ini

COPY .caddy/Caddyfile-prod /etc/caddy/Caddyfile

RUN rm -rf /app

COPY --from=app-compilation /app /app
