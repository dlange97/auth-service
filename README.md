# auth-service

## Overview

Authentication and authorization microservice (login, JWT, users, roles, permissions).

## Contents

- `src/` — domain logic and controllers.
- `config/` — Symfony and security configuration.
- `migrations/` — database migrations.
- `compose.yaml` / `compose.override.yaml` — standalone Symfony template files (PostgreSQL-based, not used by the central MySQL stack).

## Run (in this project)

```bash
docker compose -f ../../my-dashboard-docker/docker-compose.yml up -d auth-php
```

## Common Operations

```bash
# Migrations
docker compose -f ../../my-dashboard-docker/docker-compose.yml exec -T auth-php php bin/console doctrine:migrations:migrate --no-interaction

# Create or update test admin user
docker compose -f ../../my-dashboard-docker/docker-compose.yml exec -T auth-php php bin/console app:create-test-user --email admin.test@micro.com --password Admin123! --firstName Admin --lastName Test --role ROLE_ADMIN --upsert
```
