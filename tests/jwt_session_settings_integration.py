#!/usr/bin/env python3
import base64
import json
import urllib.error
import urllib.request

BASE = "http://localhost:8081"
ADMIN_EMAIL = "admin.test@micro.com"
ADMIN_PASSWORD = "Admin123!"


def call(method, path, payload=None, token=None):
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"

    data = None if payload is None else json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(f"{BASE}{path}", data=data, headers=headers, method=method)

    try:
        with urllib.request.urlopen(req) as res:
            body = res.read().decode("utf-8")
            parsed = json.loads(body) if body else None
            return res.status, parsed
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8")
        try:
            parsed = json.loads(body) if body else None
        except json.JSONDecodeError:
            parsed = body
        return exc.code, parsed


def decode_payload(token):
    parts = token.split(".")
    payload = parts[1] + "=" * (-len(parts[1]) % 4)
    return json.loads(base64.urlsafe_b64decode(payload.encode("utf-8")))


def check(name, actual, expected):
    if actual != expected:
        raise SystemExit(f"FAIL {name}: expected {expected}, got {actual}")
    print(f"OK {name}: {actual}")


def main():
    status, body = call("POST", "/auth/login", {
        "email": ADMIN_EMAIL,
        "password": ADMIN_PASSWORD,
    })
    check("login", status, 200)
    token = body["token"]

    claims = decode_payload(token)
    ttl_seconds = int(claims.get("exp", 0)) - int(claims.get("iat", 0))
    check("default-jwt-ttl", ttl_seconds, 2592000)

    status, body = call("GET", "/auth/settings/jwt-session", token=token)
    check("jwt-settings-list", status, 200)
    if not isinstance(body, list) or len(body) != 1:
        raise SystemExit("FAIL jwt-settings-singleton: expected exactly one setting")
    setting_id = body[0]["id"]

    status, body = call("PATCH", f"/auth/settings/jwt-session/{setting_id}", {
        "ttlDays": 7,
    }, token=token)
    check("jwt-settings-update", status, 200)
    check("jwt-settings-ttl-days", int(round(body.get("ttlDays", 0))), 7)

    status, body = call("DELETE", f"/auth/settings/jwt-session/{setting_id}", token=token)
    check("jwt-settings-delete-protected", status, 409)

    status, body = call("POST", "/auth/settings/jwt-session", {
        "name": "replace-singleton",
        "ttlDays": 14,
    }, token=token)
    check("jwt-settings-create-upsert", status, 200)

    status, body = call("GET", "/auth/settings/jwt-session", token=token)
    check("jwt-settings-singleton-after-upsert", status, 200)
    if not isinstance(body, list) or len(body) != 1:
        raise SystemExit("FAIL jwt-settings-singleton-after-upsert: expected exactly one setting")

    print("JWT_SESSION_SETTINGS_INTEGRATION_OK")


if __name__ == "__main__":
    main()
