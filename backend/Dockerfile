FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install sqlite3

WORKDIR /app/backend

COPY backend/ /app/backend/

RUN mkdir -p /app/backend/data

ENV PORT=10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
