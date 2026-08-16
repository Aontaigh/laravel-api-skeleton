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
PHP_BIN="${PHP_BIN:-php}"

openapi_example() {
    "$PHP_BIN" "$ROOT/scripts/openapi-example-json.php" "$1"
}

merge_created_token() {
    "$PHP_BIN" "$ROOT/scripts/openapi-merge-created-token.php" "$1" "$2"
}

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
    "$PHP_BIN" "$ROOT/scripts/openapi-compare-envelope.php" "$1" "$2" "$3"
}

# Success envelopes
check UsersIndexSuccess "$(openapi_example UsersIndexSuccess)" \
  "$(api GET '/users?per_page=2&include=team,role&fields%5Busers%5D=id,name&fields%5Bteams%5D=id,name&fields%5Broles%5D=id,name')"

check UserShowSuccess "$(openapi_example UserShowSuccess)" \
  "$(api GET '/users/1?include=team,role')"

check MeShowSuccess "$(openapi_example MeShowSuccess)" \
  "$(api GET '/me?include=team,role' "$TEST_TOKEN")"

check AuditLogsIndexSuccess "$(openapi_example AuditLogsIndexSuccess)" \
  "$(api GET '/audit-logs?per_page=1&sort=id&filter%5Bevent%5D=Login&filter%5Bsearch%5D=admin%40&include=user&fields%5Busers%5D=id,name,email')"

AUDIT_LOG_ID="$(artisan tinker --execute="echo App\\Models\\AuthAuditLog::query()->where('event','Login')->where('email','admin@example.com')->orderBy('id')->value('id');" 2>/dev/null | tail -1)"
check AuthAuditLogShowSuccess "$(openapi_example AuthAuditLogShowSuccess)" \
  "$(api GET "/audit-logs/${AUDIT_LOG_ID}?fields%5Bauth_audit_logs%5D=id,event,email,user_id,api_client_id,remember_me,created_at")"

check UserUpdateSuccess "$(openapi_example UserUpdateSuccess)" \
  "$(api PATCH '/users/2' "$ADMIN_TOKEN" '{"name":"Manager User"}')"

check RolesIndexSuccess "$(openapi_example RolesIndexSuccess)" \
  "$(api GET '/roles?per_page=2&include=permissions&fields%5Broles%5D=id,name&fields%5Bpermissions%5D=id,name')"

check RoleShowSuccess "$(openapi_example RoleShowSuccess)" \
  "$(api GET '/roles/1?include=permissions&fields%5Broles%5D=id,name&fields%5Bpermissions%5D=id,name')"

check PermissionsIndexSuccess "$(openapi_example PermissionsIndexSuccess)" \
  "$(api GET '/permissions?per_page=2&fields%5Bpermissions%5D=id,name')"

check ClientShowSuccess "$(openapi_example ClientShowSuccess)" \
  "$(api GET '/clients/1')"

api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-index-1","abilities":["tokens.list-own"]}' > /dev/null
api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-index-2","abilities":["tokens.list-own"]}' > /dev/null

check TokensIndexSuccess "$(openapi_example TokensIndexSuccess)" \
  "$(api GET '/tokens?per_page=2&sort=-id')"

TOKEN_CREATE="$(api POST '/tokens' "$ADMIN_TOKEN" '{"name":"openapi-example","abilities":["tokens.list-own"]}')"
check TokenCreateSuccess "$(openapi_example TokenCreateSuccess)" \
  "$(merge_created_token "$(openapi_example TokenCreateSuccess)" "$TOKEN_CREATE")"

check UserTokenCreateSuccess "$(openapi_example UserTokenCreateSuccess)" \
  "$(merge_created_token "$(openapi_example UserTokenCreateSuccess)" "$(api POST '/users/2/tokens' "$ADMIN_TOKEN" '{"name":"admin-issued","abilities":["tokens.list-own"]}')")"

# Error envelopes
check UnauthorizedError "$(openapi_example UnauthorizedError)" \
  "$(curl -s "${BASE}/users")"

check ForbiddenError "$(openapi_example ForbiddenError)" \
  "$(api GET '/users' "$TEST_TOKEN")"

check NotFoundError "$(openapi_example NotFoundError)" \
  "$(api GET '/users/99999')"

check ValidationErrorExample "$(openapi_example ValidationErrorExample)" \
  "$(api GET '/users/1?include=roles')"

check InvalidAbilitiesError "$(openapi_example InvalidAbilitiesError)" \
  "$(api POST '/tokens' "$ADMIN_TOKEN" '{"name":"bad","abilities":["not.real"]}')"

check UserDeleteSuccess "$(openapi_example UserDeleteSuccess)" \
  "$(api DELETE "/users/$(artisan tinker --execute="\$t=App\Models\Team::first(); \$u=App\Models\User::factory()->for(\$t)->user()->create(['name'=>'Del','email'=>'del-'.uniqid().'@example.com']); echo \$u->id;" 2>/dev/null | tail -1)")"

REVOKE_ID="$(echo "$TOKEN_CREATE" | "$PHP_BIN" -r 'echo json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR)["data"]["token"]["id"];')"
check TokenRevokeSuccess "$(openapi_example TokenRevokeSuccess)" \
  "$(api DELETE "/tokens/${REVOKE_ID}")"

echo "All OpenAPI examples verified"
