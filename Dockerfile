FROM dunglas/frankenphp:1.1.2-php8.3

WORKDIR /app

# add additional extensions here:
RUN install-php-extensions \
	xdebug
# 	intl \
# 	zip

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY .php/php.ini /usr/local/etc/php/php.ini
RUN apt-get update \
	&& apt-get install --assume-yes --quiet --no-install-recommends --purge \
		bash-completion \
		openssh-client \
		ssh \
		unzip \
		vim \
	&& rm -rf \
		/var/lib/apt/lists/* \
		/var/lib/dpkg/info/* \
		/var/lib/dpkg/status-old
