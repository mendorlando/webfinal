FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libcurl4-openssl-dev \
    && docker-php-ext-install sqlite3 curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app/backend

COPY backend/ /app/backend/

RUN mkdir -p /app/backend/data

ENV PORT=10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
