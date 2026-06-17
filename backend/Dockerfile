FROM php:8.3-cli

WORKDIR /app/backend

COPY backend/ /app/backend/

RUN mkdir -p /app/backend/data

ENV PORT=10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} router.php"]
