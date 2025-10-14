# Stage 1: Build the application
FROM composer:2 as vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Stage 2: Setup the final image with PHP and RoadRunner
FROM php:8.1-cli-alpine

# Install RoadRunner binary
RUN wget https://github.com/roadrunner-server/roadrunner/releases/download/v2.12.1/roadrunner-2.12.1-linux-amd64.tar.gz && \
    tar -xvf roadrunner-2.12.1-linux-amd64.tar.gz && \
    mv roadrunner-server /usr/local/bin/rr

# Install required PHP extensions (e.g., for mysql, redis)
RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

# Copy application code
COPY . .
# Copy vendor files from the build stage
COPY --from=vendor /app/vendor/ ./vendor/

# Expose the port RoadRunner will listen on
EXPOSE 8080

# The command to run the application
CMD ["rr", "serve", "-c", ".roadrunner.yaml"]