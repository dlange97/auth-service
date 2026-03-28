#!/bin/sh
set -e

echo "=== Auth Service: Waiting for MySQL ==="
DB_URL="${DATABASE_URL:-mysql://app:secret@mysql:3306/auth_db}"
# Extract host, port, dbname, user, pass from DATABASE_URL
DB_HOST=$(echo "$DB_URL" | sed -E 's|.*@([^:/]+).*|\1|')
DB_PORT=$(echo "$DB_URL" | sed -E 's|.*:([0-9]+)/.*|\1|')
DB_NAME=$(echo "$DB_URL" | sed -E 's|.*/([^?]+).*|\1|')
DB_USER=$(echo "$DB_URL" | sed -E 's|.*://([^:]+):.*|\1|')
DB_PASS=$(echo "$DB_URL" | sed -E 's|.*://[^:]+:([^@]+)@.*|\1|')
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASS}');" 2>/dev/null; do
    echo "  MySQL (${DB_NAME}) not ready, retrying in 2s..."
    sleep 2
done
echo "=== MySQL (${DB_NAME}) is ready ==="

cd /app

needs_composer_install() {
    if [ ! -f vendor/autoload.php ]; then
        return 0
    fi

    php -r "require 'vendor/autoload.php';" >/dev/null 2>&1 || return 0
    return 1
}

# ── Generate JWT key-pair if not present ────────────────────
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT key pair..."
    mkdir -p config/jwt
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa \
        -pkeyopt rsa_keygen_bits:4096 -pass pass:${JWT_PASSPHRASE}
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem \
        -pubout -passin pass:${JWT_PASSPHRASE}
    chmod 644 config/jwt/public.pem
    chmod 600 config/jwt/private.pem
    chown www-data:www-data config/jwt/*.pem
    echo "JWT keys generated."
fi

# ── Composer ────────────────────────────────────────────────
if needs_composer_install; then
    echo "Installing dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# ── Migrations & Cache ─────────────────────────────────────
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
php bin/console cache:clear || true

echo "=== Auth Service is ready ==="
exec "$@"
