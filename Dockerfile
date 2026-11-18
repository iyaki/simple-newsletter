# Imagen de desarrollo (devcontainer, target dev-environment). La imagen de
# producción es OCI-Nix: infrastructure/image.nix vía scripts/build-image.sh —
# devenv.lock es la única fuente de verdad del runtime de prod.
FROM dunglas/frankenphp:1.13.3-php8.5-trixie AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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
