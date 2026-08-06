#!/usr/bin/env bash
# Verify OpenAPI component examples match live API response shape and envelope fields.
# Local: Sail + seeded DB (default), or `php artisan serve` with OPENAPI_VERIFY_BASE.
# CI: sets ARTISAN_CMD=php artisan and OPENAPI_VERIFY_BASE=http://127.0.0.1:8000/api.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -n "${ARTISAN_CMD:-}" ]]; then
    :
elif [[ -x ./vendor/bin/sail ]] && command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    ARTISAN_CMD="./vendor/bin/sail artisan"
else
    ARTISAN_CMD="php artisan"
fi

BASE="${OPENAPI_VERIFY_BASE:-http://localhost/api}"

artisan() {
    # shellcheck disable=SC2086
    $ARTISAN_CMD "$@"
}

ADMIN_TOKEN="$(artisan tinker --execute="echo App\Models\User::where('email', 'admin@example.com')->first()->createToken('verify-examples')->plainTextToken;" 2>/dev/null | tail -1)"
TEST_TOKEN="$(artisan tinker --execute="echo App\Models\User::where('email', 'test@example.com')->first()->createToken('verify-examples')->plainTextToken;" 2>/dev/null | tail -1)"

api() {
    local method="$1" path="$2" token="${3:-$ADMIN_TOKEN}" body="${4:-}"
    if [[ -n "$body" ]]; then
        curl -s -X "$method" -H "Authorization: Bearer $token" -H "Content-Type: application/json" -d "$body" "${BASE}${path}"
    else
        curl -s -X "$method" -H "Authorization: Bearer $token" "${BASE}${path}"
    fi
}

check() {
    local name="$1" expected="$2" actual="$3"
    python3 - "$name" "$expected" "$actual" <<'PY'
import json, sys

name, expected_raw, actual_raw = sys.argv[1:4]
expected = json.loads(expected_raw)
actual = json.loads(actual_raw)

def keys_shape(obj):
    if isinstance(obj, dict):
        return {k: keys_shape(v) for k, v in sorted(obj.items())}
    if isinstance(obj, list):
        return [keys_shape(obj[0])] if obj else []
    return type(obj).__name__

errors = []
if keys_shape(expected) != keys_shape(actual):
    errors.append(f"shape mismatch\n  expected: {keys_shape(expected)}\n  actual:   {keys_shape(actual)}")
for key in ("status", "status_code", "message"):
    if expected.get(key) != actual.get(key):
        errors.append(f"{key}: expected {expected.get(key)!r}, got {actual.get(key)!r}")

if errors:
    print(f"FAIL {name}")
    for e in errors:
        print(f"  - {e}")
    sys.exit(1)
print(f"OK   {name}")
PY
}

# Success envelopes
check UsersIndexSuccess "$(python3 -c "import yaml; print(__import__('json').dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UsersIndexSuccess']['value']))")" \
  "$(api GET '/users?per_page=2&include=team,role&fields%5Busers%5D=id,name&fields%5Bteams%5D=id,name&fields%5Broles%5D=id,name')"

check UserShowSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UserShowSuccess']['value']))")" \
  "$(api GET '/users/1?include=team,role')"

check UserUpdateSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UserUpdateSuccess']['value']))")" \
  "$(api PATCH '/users/2' "$ADMIN_TOKEN" '{"name":"Manager User"}')"

check RolesIndexSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['RolesIndexSuccess']['value']))")" \
  "$(api GET '/roles?per_page=2&include=permissions&fields%5Broles%5D=id,name&fields%5Bpermissions%5D=id,name')"

check RoleShowSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['RoleShowSuccess']['value']))")" \
  "$(api GET '/roles/1?include=permissions&fields%5Broles%5D=id,name&fields%5Bpermissions%5D=id,name')"

api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-index-1","abilities":["tokens.list-own"]}'
api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-index-2","abilities":["tokens.list-own"]}'

check TokensIndexSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['TokensIndexSuccess']['value']))")" \
  "$(api GET '/tokens?per_page=2&sort=-id')"

TOKEN_CREATE="$(api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-example","abilities":["tokens.list-own"]}')"
check TokenCreateSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['TokenCreateSuccess']['value']))")" \
  "$(python3 -c "import json,sys; ex=json.loads(sys.argv[1]); live=json.loads(sys.argv[2]); live['data']['token']['id']=ex['data']['token']['id']; live['data']['token']['created_at']=ex['data']['token']['created_at']; live['data']['plain_text_token']=ex['data']['plain_text_token']; print(json.dumps(live))" \
    "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['TokenCreateSuccess']['value']))")" \
    "$TOKEN_CREATE")"

check UserTokenCreateSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UserTokenCreateSuccess']['value']))")" \
  "$(python3 -c "import json,sys; ex=json.loads(sys.argv[1]); live=json.loads(sys.argv[2]); live['data']['token']['id']=ex['data']['token']['id']; live['data']['token']['created_at']=ex['data']['token']['created_at']; live['data']['plain_text_token']=ex['data']['plain_text_token']; print(json.dumps(live))" \
    "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UserTokenCreateSuccess']['value']))")" \
    "$(api POST '/users/2/tokens' "$ADMIN_TOKEN" '{"name":"admin-issued","abilities":["tokens.list-own"]}')")"

# Error envelopes
check UnauthorizedError "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UnauthorizedError']['value']))")" \
  "$(curl -s "${BASE}/users")"

check ForbiddenError "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['ForbiddenError']['value']))")" \
  "$(api GET '/users' "$TEST_TOKEN")"

check NotFoundError "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['NotFoundError']['value']))")" \
  "$(api GET '/users/99999')"

check ValidationErrorExample "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['ValidationErrorExample']['value']))")" \
  "$(api GET '/users/1?include=roles')"

check InvalidAbilitiesError "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['InvalidAbilitiesError']['value']))")" \
  "$(api POST '/tokens' "$ADMIN_TOKEN" '{"name":"bad","abilities":["not.real"]}')"

check UserDeleteSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['UserDeleteSuccess']['value']))")" \
  "$(api DELETE "/users/$(artisan tinker --execute="\$t=App\Models\Team::first(); \$u=App\Models\User::factory()->for(\$t)->user()->create(['name'=>'Del','email'=>'del-'.uniqid().'@example.com']); echo \$u->id;" 2>/dev/null | tail -1)")"

REVOKE_ID="$(echo "$TOKEN_CREATE" | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['token']['id'])")"
check TokenRevokeSuccess "$(python3 -c "import yaml,json; print(json.dumps(yaml.safe_load(open('docs/openapi.yaml'))['components']['examples']['TokenRevokeSuccess']['value']))")" \
  "$(api DELETE "/tokens/${REVOKE_ID}")"

echo "All OpenAPI examples verified"
