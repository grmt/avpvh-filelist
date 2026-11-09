# WordPress with the PHP LDAP extension (as on the servers) and WP-CLI, for the integration tests.
FROM wordpress:php8.4-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libldap2-dev \
	&& docker-php-ext-install ldap \
	&& rm -rf /var/lib/apt/lists/* \
	&& curl -fsSL --retry 5 --retry-all-errors --retry-delay 10 -o /usr/local/bin/wp https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar \
	&& chmod +x /usr/local/bin/wp
