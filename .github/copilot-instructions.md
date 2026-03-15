# Copilot Instructions - Auth Service

Scope: This repository only (my-dashboard-backend/auth-service).

## Stack
- PHP 8.2+, Symfony, Doctrine, JWT auth.

## Rules
- Keep controllers thin; move business logic to services.
- Use typed DTOs for request/response boundaries.
- Use strict typing and PSR-12 style.
- Keep OpenAPI/Swagger docs updated for every /api endpoint change.
- Never introduce cross-service DB coupling.

## Quality
- Run service tests after changes: docker compose exec auth-php bin/phpunit.
- If schema/entity changes are made, ensure migrations are generated and valid.
