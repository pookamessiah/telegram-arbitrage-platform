FROM php:8.2-cli

# Install system dependencies, python3, and pip
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install the PHP MySQL PDO driver
RUN docker-php-ext-install pdo pdo_mysql

# Install Pyrogram and tgcrypto for Telegram session generation
RUN pip3 install --no-cache-dir pyrogram tgcrypto --break-system-packages

# Copy the entire repository into /app
COPY . /app/

# Set working directory to your frontend-php folder
WORKDIR /app/frontend-php

# Expose port and run PHP built-in server with the custom router
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} router.php"]
