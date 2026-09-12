# =====================================================================
# swapapp-symfony — CI / demo Dockerfile (NEW file, app logic untouched)
# ---------------------------------------------------------------------
# WHY THIS FILE EXISTS:
#   compose.yaml in this repo only defines infrastructure services
#   (database/postgres + mailer/mailpit). There is NO app image, so
#   Trivy (container scan), Syft (SBOM) and ZAP (DAST) have nothing to
#   scan. This Dockerfile builds a runnable app image ONLY for the
#   security pipeline. Local dev via `docker compose up` is unchanged.
#
# WHAT IT DOES:
#   php:8.1-apache + required Symfony extensions -> composer install
#   -> DocumentRoot points at /public (Symfony front controller).
#   Exposes 8000 to match the ZAP step in security-pipeline.yml.
# =====================================================================

FROM php:8.1-apache

# System deps + PHP extensions Symfony/Doctrine need. mysql + pgsql drivers
# stay (repo .env uses MySQL, compose.yaml ships Postgres) AND sqlite is
# added: the CI container boots with a local sqlite DB (see ENV below) so
# the DAST target answers HTTP 200 instead of 500 (no DB server in CI).
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libicu-dev libzip-dev libonig-dev libpq-dev libsqlite3-dev \
    && docker-php-ext-install intl mbstring mysqli pdo pdo_mysql pdo_pgsql pdo_sqlite zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Composer (official image, NOT the committed composer.phar binaries —
# see docs/DEVSECOPS.md findings: those .phar files should be removed).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (better layer cache), then copy the app.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress || true

# Copy the rest (src/, templates/, public/, config/ — all unmodified).
COPY . .

# Apache must serve public/ (Symfony front controller: public/index.php).
# CI-only runtime env (overridable at `docker run -e`, compose, or GH secrets):
# sqlite file DB so the app boots WITHOUT any database server (DAST target
# must return pages, not Doctrine connection errors). No migrations exist in
# this repo, so schema is created directly from entities (best-effort: every
# step tolerates failure so a half-ready schema never breaks `docker build`).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=prod \
    DATABASE_URL="sqlite:////var/www/html/var/ci.db" \
    MAILER_DSN="null://null"
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && mkdir -p var/cache var/log \
    && php bin/console doctrine:database:create --env=prod --if-not-exists || true \
    && php bin/console doctrine:schema:create --env=prod --no-interaction || true \
    && php bin/console cache:clear --env=prod || true \
    && chown -R www-data:www-data var public

EXPOSE 8000
# apache runs on 80 inside; CI maps 8000:80 (see workflow DAST step).
CMD ["apache2-foreground"]
