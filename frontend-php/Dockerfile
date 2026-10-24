FROM php:8.2-cli

# Install system dependencies, python3, and pip
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Pyrogram and tgcrypto for Telegram session generation
RUN pip3 install --no-cache-dir pyrogram tgcrypto --break-system-packages

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Expose port and run PHP built-in server binding to Railway's dynamic $PORT
EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
