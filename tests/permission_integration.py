#!/usr/bin/env python3
"""
Integration test: permission enforcement for restricted-role users.

Verifies that a user assigned a custom role with only 'dashboard.view' permission:
  - receives exactly that permission from GET /auth/me  (no ROLE_USER bleed-through)
  - gets HTTP 403 on every endpoint that requires a higher permission
  - can still access their own profile (GET /auth/me → 200)

Run from the auth-service directory:
    python3 tests/permission_integration.py

Requires ADMIN_EMAIL and ADMIN_PASSWORD set in .env.dev (or environment).
"""

import base64
import json
import os
import urllib.error
import urllib.request
from pathlib import Path


# ── env loading ────────────────────────────────────────────────────────────────

def load_env_file(path: Path) -> None:
    if not path.exists():
        return
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


SERVICE_DIR = Path(__file__).resolve().parents[1]
load_env_file(SERVICE_DIR / ".env")
load_env_file(SERVICE_DIR / ".env.dev")

BASE = os.getenv("BASE_URL", "http://localhost:8081")
ADMIN_EMAIL = os.getenv("ADMIN_EMAIL", "")
ADMIN_PASSWORD = os.getenv("ADMIN_PASSWORD", "")

if not ADMIN_EMAIL or not ADMIN_PASSWORD:
    raise SystemExit("Missing ADMIN_EMAIL or ADMIN_PASSWORD in auth-service/.env.dev")

# Fixed credentials for the disposable test user created by this suite.
# The user is created once and reused on subsequent runs (idempotent).
TEST_USER_EMAIL = "permission.tester@integration.test"
TEST_USER_PASSWORD = "PermInteg1!secure"
TEST_ROLE_NAME = "Integration Test Limited"
TEST_ROLE_SLUG = "ROLE_INTEGRATION_TEST_LIMITED"


# ── HTTP helpers ───────────────────────────────────────────────────────────────

def call(method: str, path: str, payload=None, token: str | None = None):
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    data = None if payload is None else json.dumps(payload).encode()
    req = urllib.request.Request(f"{BASE}{path}", data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as res:
            body = res.read().decode()
            return res.status, (json.loads(body) if body else None)
    except urllib.error.HTTPError as exc:
        body = exc.read().decode()
        try:
            parsed = json.loads(body) if body else None
        except json.JSONDecodeError:
            parsed = body
        return exc.code, parsed


def check(label: str, actual, expected) -> None:
    if actual != expected:
        raise SystemExit(f"FAIL {label}: expected {expected!r}, got {actual!r}")
    print(f"  OK  {label}: {actual!r}")


def check_not_in(label: str, value, container) -> None:
    if value in container:
        raise SystemExit(f"FAIL {label}: {value!r} should NOT be in {container!r}")
    print(f"  OK  {label}: {value!r} absent")


def check_in(label: str, value, container) -> None:
    if value not in container:
        raise SystemExit(f"FAIL {label}: {value!r} should be in {container!r}")
    print(f"  OK  {label}: {value!r} present")


# ── setup helpers ──────────────────────────────────────────────────────────────

def ensure_limited_role(admin_token: str) -> None:
    """Create the test role (only dashboard.view) if it does not yet exist."""
    status, body = call("POST", "/auth/roles", {
        "name": TEST_ROLE_NAME,
        "slug": TEST_ROLE_SLUG.lower(),
        "permissions": ["dashboard.view"],
    }, token=admin_token)

    if status == 201:
        print(f"  --  created test role {TEST_ROLE_SLUG}")
    elif status == 409:
        print(f"  --  test role {TEST_ROLE_SLUG} already exists")
    else:
        raise SystemExit(f"FAIL ensure-role: unexpected {status}: {body}")


def ensure_limited_user(admin_token: str) -> str:
    """
    Create the test user with the limited role.
    If the user already exists (409), log in with the known password to obtain
    their token and then re-assign the limited role to guarantee the correct state.
    Returns the limited user's JWT.
    """
    status, body = call("POST", "/auth/users", {
        "email": TEST_USER_EMAIL,
        "password": TEST_USER_PASSWORD,
        "firstName": "Permission",
        "lastName": "Tester",
        "role": TEST_ROLE_SLUG,
    }, token=admin_token)

    if status == 201:
        print(f"  --  created test user {TEST_USER_EMAIL}")
        user_id = body["user"]["id"]
    elif status == 409:
        print(f"  --  test user already exists, logging in to retrieve id…")
        # Login to get the token, then fetch own profile for the id
        ls, lb = call("POST", "/auth/login", {
            "email": TEST_USER_EMAIL, "password": TEST_USER_PASSWORD,
        })
        if ls != 200:
            raise SystemExit(f"FAIL ensure-user-login: expected 200, got {ls}: {lb}")
        existing_token = lb["token"]
        ps, pb = call("GET", "/auth/me", token=existing_token)
        if ps != 200:
            raise SystemExit(f"FAIL ensure-user-me: expected 200, got {ps}: {pb}")
        user_id = pb["user"]["id"]
        # Re-assign the limited role to guarantee correct state
        rs, rb = call("PATCH", f"/auth/users/{user_id}/role", {"role": TEST_ROLE_SLUG}, token=admin_token)
        if rs not in (200, 201):
            raise SystemExit(f"FAIL ensure-role-assign: expected 200, got {rs}: {rb}")
        print(f"  --  re-assigned {TEST_ROLE_SLUG} to existing user")
    else:
        raise SystemExit(f"FAIL ensure-user: unexpected {status}: {body}")

    # Login as the limited user and return the fresh token (which now contains
    # only the permissions derived from the fixed PermissionService).
    ls, lb = call("POST", "/auth/login", {
        "email": TEST_USER_EMAIL,
        "password": TEST_USER_PASSWORD,
    })
    if ls != 200:
        raise SystemExit(f"FAIL limited-user-login: expected 200, got {ls}: {lb}")
    print(f"  --  logged in as {TEST_USER_EMAIL}")
    return lb["token"]


# ── tests ──────────────────────────────────────────────────────────────────────

def test_me_returns_only_dashboard_permission(token: str) -> None:
    """GET /auth/me must return exactly ['dashboard.view'] for the limited user."""
    print("\n[1] Own profile returns only allowed permissions")
    status, body = call("GET", "/auth/me", token=token)
    check("me-status", status, 200)

    permissions: list = body["user"]["permissions"]
    if permissions != ["dashboard.view"]:
        raise SystemExit(
            f"FAIL me-permissions: expected [\"dashboard.view\"] only, got {permissions}\n"
            "Hint: Ensure PermissionService.getPermissionsForUser() uses getStoredRoles() "
            "instead of getRoles() to prevent implicit ROLE_USER permission expansion."
        )
    print(f"  OK  me-permissions: {permissions}")


def test_restricted_endpoints_return_403(token: str) -> None:
    """Endpoints requiring permissions not granted to the limited user must return 403."""
    print("\n[2] Restricted endpoints return 403")

    cases = [
        ("GET",  "/auth/users",                "users.view"),
        ("GET",  "/auth/settings/jwt-session", "settings.view"),
        ("GET",  "/auth/roles",                "settings.view"),
        ("GET",  "/auth/settings/access",      "settings.view"),
    ]

    for method, path, required_permission in cases:
        status, _ = call(method, path, token=token)
        check(f"{method}-{path}-forbidden (needs {required_permission})", status, 403)


def test_own_profile_accessible(token: str) -> None:
    """GET /auth/me must succeed (200) regardless of role — it is the user's own profile."""
    print("\n[3] Own profile endpoint always accessible")
    status, body = call("GET", "/auth/me", token=token)
    check("own-profile-accessible", status, 200)
    check_in("role-in-profile", TEST_ROLE_SLUG, body["user"]["roles"])


def test_validate_accessible(token: str) -> None:
    """POST /auth/validate must succeed for any authenticated user."""
    print("\n[4] Token validation accessible")
    status, body = call("POST", "/auth/validate", token=token)
    check("validate-accessible", status, 200)
    check("validate-flag", body["valid"], True)


def test_higher_privilege_role_grants_extra_permissions(admin_token: str) -> None:
    """
    Sanity check: the admin (ROLE_ADMIN) must have users.view and settings.view,
    confirming the permission derivation works correctly for system roles too.
    """
    print("\n[5] Admin retains full permissions")
    status, body = call("GET", "/auth/me", token=admin_token)
    check("admin-me-status", status, 200)
    permissions = body["user"]["permissions"]
    check_in("admin-has-users.view",        "users.view",          permissions)
    check_in("admin-has-settings.view",     "settings.view",       permissions)
    check_in("admin-has-users.assign_roles","users.assign_roles",   permissions)
    # Admin must also be able to call the restricted endpoints
    status, _ = call("GET", "/auth/users", token=admin_token)
    check("admin-GET-users-allowed", status, 200)


# ── entrypoint ─────────────────────────────────────────────────────────────────

def main() -> None:
    print("=== Permission Integration Test ===\n")

    # -- Setup ----------------------------------------------------------------
    print("[setup] Login as admin")
    status, body = call("POST", "/auth/login", {
        "email": ADMIN_EMAIL,
        "password": ADMIN_PASSWORD,
    })
    check("admin-login", status, 200)
    admin_token: str = body["token"]

    print("\n[setup] Ensure test role exists")
    ensure_limited_role(admin_token)

    print("\n[setup] Ensure test user exists with limited role")
    limited_token = ensure_limited_user(admin_token)

    # -- Assertions -----------------------------------------------------------
    test_me_returns_only_dashboard_permission(limited_token)
    test_restricted_endpoints_return_403(limited_token)
    test_own_profile_accessible(limited_token)
    test_validate_accessible(limited_token)
    test_higher_privilege_role_grants_extra_permissions(admin_token)

    print("\n=== PERMISSION_INTEGRATION_OK ===")


if __name__ == "__main__":
    main()
