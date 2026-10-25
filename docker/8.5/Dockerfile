FROM ubuntu:24.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
# The Node release `.nvmrc` names: 24.22.1, the most recent LTS (Krypton), which bundles npm
# 11.19.1. NodeSource publishes only the newest patch per line, which is how the image drifted off
# the pin; the install below takes the official tarball for this exact version and the guard after
# it fails the build on any other. npm is left as that tarball ships it, because the pinned Node
# determines npm (11.19.1 here) and one pin therefore covers both: upgrading npm separately here
# would run the image on a different npm than the runner's `npm ci` uses.
# `scripts/lint/check-php-version-parity.php` holds this argument against `.nvmrc`.
ARG NODE_VERSION=24.22.1
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=18
ARG PHP_EXTENSIONS=""
# The patch floor `composer.json` declares and every gate installs. The sury.org PPA below is
# unpinned, so the installed patch is whatever that mirror served at build time; the guard after
# the install fails the build rather than letting a stale patch through to fail later as a
# `platform_check.php` fatal.
ARG PHP_VERSION=8.5.12

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" > /etc/apt/sources.list.d/php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.5-cli \
        php8.5-dev \
        php8.5-pgsql \
        php8.5-sqlite3 \
        php8.5-gd \
        php8.5-curl \
        php8.5-mongodb \
        php8.5-imap \
        php8.5-mysql \
        php8.5-mbstring \
        php8.5-xml \
        php8.5-zip \
        php8.5-bcmath \
        php8.5-soap \
        php8.5-intl \
        php8.5-readline \
        php8.5-ldap \
        php8.5-msgpack \
        php8.5-igbinary \
        php8.5-redis \
        php8.5-swoole \
        php8.5-memcached \
        php8.5-pcov \
        php8.5-imagick \
        php8.5-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && COMPOSER_HOME=/usr/local/share/composer composer global require cpx/cpx:^2.0 \
    && ln -s /usr/local/share/composer/vendor/bin/cpx /usr/local/bin/cpx \
    && arch="$(dpkg --print-architecture)" \
    && case "$arch" in amd64) node_arch=x64 ;; arm64) node_arch=arm64 ;; *) echo "No Node Tarball For $arch" >&2; exit 1 ;; esac \
    && curl -fsSL "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${node_arch}.tar.gz" -o /tmp/node.tar.gz \
    && tar -xzf /tmp/node.tar.gz -C /usr/local --strip-components=1 --no-same-owner \
    && rm -f /tmp/node.tar.gz \
    && npm install -g pnpm bun corepack \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    && npx -y playwright install-deps \
    && curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get install -y postgresql-client-$POSTGRES_VERSION \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN if [ -n "$PHP_EXTENSIONS" ]; then \
    apt-get update \
    && apt-get install -y $(for ext in $PHP_EXTENSIONS; do echo "php8.5-$ext"; done) \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*; \
fi

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.5

# Fail here rather than at `composer install` later: the floor is what `composer.json` declares, and
# the PPA above is unpinned, so a mirror serving an older patch would otherwise bake a dev image
# that cannot install the lock. `scripts/lint/check-php-version-parity.php` gates the same argument
# against `composer.json` and `.php-version`, so this is the build-time half of that parity.
RUN php -r "if (version_compare(PHP_VERSION, '${PHP_VERSION}', '<')) { fwrite(STDERR, 'PHP '.PHP_VERSION.' is below the required ${PHP_VERSION}'.PHP_EOL); exit(1); }"

# The same guard for Node, whose tarball is addressed by exact version: a mismatch means the pin and
# the install have drifted apart, which would run every JavaScript gate on a toolchain no gate pins.
# `scripts/lint/check-php-version-parity.php` holds this argument against `.nvmrc`.
RUN test "$(node -v)" = "v${NODE_VERSION}" || { echo "Node $(node -v) is not the pinned v${NODE_VERSION}" >&2; exit 1; }

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
# `node_modules` is a named volume at runtime (see `compose.yaml`), and Docker copies the
# ownership of an empty volume's mount point out of the image. Creating it here covers a host
# whose uid is the build-time 1337; `start-container` re-chowns the mount point at every boot
# for the hosts where Sail remaps `sail` to `$WWWUSER` instead, which is most of them.
RUN install -d -o sail -g sail -m 0755 /var/www/html/node_modules
RUN git config --global --add safe.directory /var/www/html

COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.5/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

# Sail's local dev image drops privileges at runtime via gosu in start-container
# (SUPERVISOR_PHP_USER=sail), so it intentionally ships no static USER instruction.
# nosemgrep: dockerfile.security.missing-user-entrypoint.missing-user-entrypoint
ENTRYPOINT ["start-container"]
