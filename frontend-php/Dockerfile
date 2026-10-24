FROM php:8.2-apache

# Install system dependencies, python3, and pip
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Fix Apache MPM conflict: ensure only mpm_prefork is active
RUN a2dismod -f mpm_event mpm_worker && a2enmod mpm_prefork

# Install Pyrogram and tgcrypto for Telegram session generation
RUN pip3 install --no-cache-dir pyrogram tgcrypto --break-system-packages

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html
