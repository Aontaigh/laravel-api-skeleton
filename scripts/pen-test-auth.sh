#!/usr/bin/env bash
# Adversarial curl probes for auth endpoints — run against local Sail (http://localhost/api).
#
# Covers: enumeration, injection, rate limits, token abuse, remember-me + CSRF,
# suspensions, soft-delete, read endpoints (audit-logs, permissions, clients),
# OAuth client-credentials, queued audit persistence, web-session registry
# (IDOR, scope, surgical revoke vs global logout), and retired flat auth paths.
#
# Usage:
#   ./vendor/bin/sail artisan migrate:fresh --seed
#   bash scripts/pen-test-auth.sh
#
# Optional env:
#   PEN_TEST_BASE=http://localhost/api
#   PEN_TEST_HOST=http://localhost
#   PEN_TEST_SAIL=./vendor/bin/sail   # empty to skip tinker-backed checks
#
# Prerequisites (Sail):
#   ./vendor/bin/sail artisan migrate:fresh --seed
#   DB_HOST=mysql in .env (or the app cannot reach MySQL from the container)
#   SESSION_DRIVER=redis for cookie/CSRF probes; array driver skips stateful flows
#   and falls back to tinker-seeded web_sessions rows for registry attacks.
#   CACHE_STORE=redis enables full stateless 2FA completion; array uses tinker tokens.
#
# Diagnostic copy follows php-quality (CLI and Diagnostic Errors): Title Case
# headlines, no trailing full stop; detail after a colon when needed. API message
# assertions use the canonical ApiResponse / ApiExceptionRenderer strings from
# php-validation-responses.
set -uo pipefail
set -o errtrace

# Canonical API envelope messages (ApiExceptionRenderer / ApiResponse).
MSG_UNAUTHENTICATED='Unauthenticated'
MSG_FORBIDDEN='Forbidden'
MSG_NOT_FOUND='Resource Not Found'
MSG_VALIDATION_FAILED='Validation Failed'
MSG_TOO_MANY_REQUESTS='Too Many Requests'
MSG_SERVER_ERROR='Server Error'
MSG_BAD_REQUEST='Bad Request'
MSG_INVALID_CREDENTIALS='Invalid Credentials'
MSG_ACCOUNT_SUSPENDED='Account Suspended'

BASE="${PEN_TEST_BASE:-http://localhost/api}"
HOST="${PEN_TEST_HOST:-http://localhost}"
SAIL="${PEN_TEST_SAIL:-./vendor/bin/sail}"
COOKIE_JAR="$(mktemp)"
BODY_FILE="$(mktemp)"

# Success-path registers must clear Password::defaults() (incl. uncompromised()).
# No literal `$` — keep it POSIX/double-quote safe.
STRONG_PASS='Xq7#mK2vL9pTzW4Q'

PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

cleanup() {
    rm -f "$COOKIE_JAR" "$BODY_FILE"
}
trap cleanup EXIT

pass() {
    PASS_COUNT=$((PASS_COUNT + 1))
    echo "PASS  $1"
}

fail() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    echo "FAIL  $1" >&2
}

warn() {
    WARN_COUNT=$((WARN_COUNT + 1))
    echo "WARN  $1: $2" >&2
}

info() {
    echo "INFO  $1"
}

artisan_tinker() {
    if [[ -z "$SAIL" || ! -x "$SAIL" ]]; then
        echo "skip"
        return 1
    fi

    # CI .env often sets DB_HOST=127.0.0.1; inside Sail the host is `mysql`.
    "$SAIL" exec -e DB_HOST=mysql laravel.test php artisan tinker --execute="$1" 2>/dev/null | tail -1
}

status_code() {
    curl --globoff -s -o "$BODY_FILE" -w '%{http_code}' "$@"
}

post_json() {
    status_code -X POST "$1" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        "${@:2}" > /dev/null
}

post_json_status() {
    post_json "$@"
    json_status
}

# --- Authenticated request helpers (centralise the Bearer header) ---

auth_get() {
    status_code -X GET "$1" -H "Accept: application/json" -H "Authorization: Bearer $2"
}

auth_post() {
    status_code -X POST "$1" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer $2" \
        "${@:3}"
}

auth_delete() {
    status_code -X DELETE "$1" -H "Accept: application/json" -H "Authorization: Bearer $2"
}

# --- JSON helpers (guarded against non-JSON bodies) ---

json_status() {
    python3 -c "
import json,sys
try:
    print(json.load(open('$BODY_FILE')).get('status_code', 0))
except Exception:
    print(0)
" 2>/dev/null || echo "0"
}

json_errors_email() {
    python3 -c "
import json
try:
    d=json.load(open('$BODY_FILE'))
    errs=d.get('meta',{}).get('errors',{}).get('email',[])
    print(errs[0] if errs else '')
except Exception:
    print('')
" 2>/dev/null || echo ""
}

json_path() {
    # json_path <dotted.path> — print a nested value from the body file, or ''.
    python3 -c "
import json
try:
    d=json.load(open('$BODY_FILE'))
    cur=d
    for part in '$1'.split('.'):
        if isinstance(cur, dict):
            cur=cur.get(part)
        else:
            cur=None
            break
    print('' if cur is None else cur)
except Exception:
    print('')
" 2>/dev/null || echo ""
}

json_message() {
    python3 -c "
import json
try:
    print(json.load(open('$BODY_FILE')).get('message',''))
except Exception:
    print('')
" 2>/dev/null || echo ""
}

json_token() {
    python3 -c "
import json,sys
try:
    d=json.load(sys.stdin)
    print(d.get('data',{}).get('plain_text_token','') or '')
except Exception:
    print('')
" 2>/dev/null || echo ""
}

expect_code() {
    local label="$1"
    local expected="$2"
    local actual="$3"

    if [[ "$actual" == "$expected" ]]; then
        pass "$label ($actual)"
    else
        fail "$label: Expected HTTP $expected, Got $actual"
    fi
}

expect_not_500() {
    local label="$1"
    local code="$2"

    if [[ "$code" == "500" ]]; then
        fail "$label: Returned HTTP 500 ($MSG_SERVER_ERROR)"
    else
        pass "$label Returned $code (Not 500)"
    fi
}

# Flush the rate-limiter buckets so functional probes later in the run are not
# throttled by earlier sections. Rate limiting itself is asserted in section 4;
# the rest of the script only needs the endpoints reachable.
reset_rate_limits() {
    artisan_tinker "Illuminate\\Support\\Facades\\RateLimiter::clear('127.0.0.1|admin@example.com'); Illuminate\\Support\\Facades\\RateLimiter::clear('127.0.0.1|manager@example.com'); Illuminate\\Support\\Facades\\Cache::flush(); echo 'cleared';" > /dev/null
}

begin_stateful_session() {
    curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" \
        -H "Origin: http://localhost" \
        -H "Referer: http://localhost/" \
        "${HOST}/sanctum/csrf-cookie" -o /dev/null
}

read_xsrf_token() {
    export COOKIE_JAR
    python3 - <<'PY'
import http.cookiejar
import os
import sys
import urllib.parse

jar = http.cookiejar.MozillaCookieJar(os.environ["COOKIE_JAR"])
try:
    jar.load(ignore_discard=True, ignore_expires=True)
except Exception:
    sys.exit(1)

for cookie in jar:
    if cookie.name == "XSRF-TOKEN":
        print(urllib.parse.unquote(cookie.value))
        sys.exit(0)

sys.exit(1)
PY
}

login_token() {
    local email="$1"
    local password="$2"
    local extra_json="${3:-}"

    if [[ -n "$extra_json" ]]; then
        curl --globoff -s -X POST "$BASE/auth/login" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "{\"email\":\"${email}\",\"password\":\"${password}\",${extra_json}}"
    else
        curl --globoff -s -X POST "$BASE/auth/login" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "{\"email\":\"${email}\",\"password\":\"${password}\"}"
    fi | json_token
}

inject_two_factor_code() {
    local email="$1"
    local code="${2:-654321}"

    artisan_tinker "
\$id = App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->value('id');
if (! \$id) { echo ''; return; }
Illuminate\\Support\\Facades\\Cache::put('two-factor:'.\$id, [
    'code_hash' => Illuminate\\Support\\Facades\\Hash::make('${code}'),
    'attempts' => 0,
    'expires_at' => now()->addMinutes(5)->timestamp,
], 300);
echo '${code}';
"
}

# Register (MFA-enrolled) then obtain a bearer token for follow-on probes.
# When CACHE_STORE=array the opaque 2FA pending payload cannot survive the next
# HTTP request, so we issue via tinker after exercising the register endpoint.
register_and_login_token() {
    local email="$1"
    local password="$2"
    local name="${3:-Pen Test User}"

    post_json "$BASE/auth/register" \
        -d "{\"name\":\"${name}\",\"email\":\"${email}\",\"password\":\"${password}\",\"password_confirmation\":\"${password}\"}"
    if [[ "$(json_status)" != "201" ]]; then
        echo ""
        return 1
    fi

    local tfa_required
    tfa_required="$(json_path 'data.two_factor_required' | tr '[:upper:]' '[:lower:]')"
    if [[ "$tfa_required" != "true" ]]; then
        json_path 'data.plain_text_token'
        return 0
    fi

    local cache_driver
    cache_driver="$(artisan_tinker "echo config('cache.default');")"
    if [[ "$cache_driver" == "array" ]]; then
        issue_token "$email"
        return 0
    fi

    local tft
    tft="$(json_path 'data.two_factor_token')"
    if [[ -z "$tft" ]]; then
        echo ""
        return 1
    fi

    post_json "$BASE/auth/two-factor/send" \
        -d "{\"channel\":\"email\",\"two_factor_token\":\"${tft}\"}"
    inject_two_factor_code "$email" "654321" > /dev/null

    curl --globoff -s -X POST "$BASE/auth/two-factor/verify" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{\"code\":\"654321\",\"two_factor_token\":\"${tft}\"}" | json_token
}

session_driver() {
    artisan_tinker "echo config('session.driver');"
}

stateful_sessions_supported() {
    [[ "$(session_driver)" != "array" ]]
}

seed_web_session() {
    local email="$1"
    local remember="${2:-false}"

    artisan_tinker "
\$user = App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->first();
if (! \$user) { echo ''; return; }
\$session = App\\Models\\WebSession::factory()->for(\$user)->create([
    'remember_me' => ${remember},
]);
echo \$session->id;
"
}

# Prefer a real cookie session; fall back to a registry row when SESSION_DRIVER=array
# or when cookie login cannot complete (e.g. MFA-enrolled accounts awaiting 2FA).
ensure_web_session_row() {
    local email="$1"
    local password="$2"
    local remember="${3:-true}"
    local session_id=""

    if stateful_sessions_supported; then
        stateful_login "$email" "$password" "$remember" > /dev/null
        session_id="$(web_session_id_for_user "$email")"
        if [[ -n "$session_id" ]]; then
            echo "$session_id"
            return 0
        fi
    fi

    seed_web_session "$email" "$remember" > /dev/null

    web_session_id_for_user "$email"
}

issue_token() {
    local email="$1"
    artisan_tinker "echo App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->first()?->createToken('pen-test')->plainTextToken ?? '';"
}

# Drain queued auth-audit listeners (RecordAuthAuditLog is ShouldQueue).
drain_audit_queue() {
    if [[ -z "$SAIL" || ! -x "$SAIL" ]]; then
        return 0
    fi

    "$SAIL" exec -e DB_HOST=mysql laravel.test php artisan queue:work --stop-when-empty --max-time=20 -n -q 2>/dev/null || true
}

suspend_user() {
    local email="$1"
    artisan_tinker "App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->first()?->forceFill(['suspended_at'=>now()])->save(); echo 'suspended';"
}

oauth_token() {
    curl -s -X POST "$BASE/oauth/token" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$1" | json_token
}

json_data_count() {
    python3 -c "
import json
try:
    d=json.load(open('$BODY_FILE'))
    data=d.get('data',[])
    print(len(data) if isinstance(data, list) else 0)
except Exception:
    print(0)
" 2>/dev/null || echo "0"
}

web_session_id_for_user() {
    local email="$1"
    artisan_tinker "echo App\\Models\\WebSession::query()->where('user_id', App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->value('id'))->whereNull('revoked_at')->orderBy('id')->value('id') ?? '';"
}

active_web_session_count() {
    artisan_tinker "echo App\\Models\\WebSession::whereNull('revoked_at')->count();"
}

revoked_web_session_count_for_user() {
    local email="$1"
    artisan_tinker "echo App\\Models\\WebSession::where('user_id', App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${email}'))->value('id'))->whereNotNull('revoked_at')->count();"
}

# Stateful SPA login — returns plain_text_token on stdout, sets COOKIE_JAR.
stateful_login() {
    local email="$1"
    local password="$2"
    local remember="${3:-false}"

    rm -f "$COOKIE_JAR"
    COOKIE_JAR="$(mktemp)"
    begin_stateful_session
    local xsrf
    xsrf="$(read_xsrf_token)"

    local payload
    if [[ "$remember" == "true" ]]; then
        payload="{\"email\":\"${email}\",\"password\":\"${password}\",\"remember\":true}"
    else
        payload="{\"email\":\"${email}\",\"password\":\"${password}\"}"
    fi

    curl --globoff -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/auth/login" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Origin: http://localhost" \
        -H "Referer: http://localhost/" \
        -H "X-XSRF-TOKEN: ${xsrf}" \
        -d "$payload" -o "$BODY_FILE"

    json_path 'data.plain_text_token'
}

echo "=== Auth Pen Test (adversarial) ==="
echo "Base: $BASE"
echo "Host: $HOST"
if stateful_sessions_supported; then
    echo "Session driver: $(session_driver) (stateful probes enabled)"
else
    echo "Session driver: $(session_driver) (stateful probes use tinker-seeded registry rows)"
fi
echo ""

# --- 1. Account enumeration ---
echo "--- 1. Account enumeration ---"
post_json "$BASE/auth/login" -d "{\"email\":\"missing@example.com\",\"password\":\"${STRONG_PASS}\"}"
UNKNOWN_MSG="$(json_errors_email)"

post_json "$BASE/auth/login" -d '{"email":"admin@example.com","password":"WrongPass1"}'
WRONG_MSG="$(json_errors_email)"

if [[ "$UNKNOWN_MSG" == "$WRONG_MSG" && "$UNKNOWN_MSG" == "$MSG_INVALID_CREDENTIALS" ]]; then
    pass "Login Unknown vs Wrong Password Identical Generic Message"
else
    fail "Login Enumeration Leak: unknown='$UNKNOWN_MSG' wrong='$WRONG_MSG'"
fi

post_json "$BASE/auth/register" -d "{\"name\":\"Probe\",\"email\":\"admin@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
REGISTER_MSG="$(json_errors_email)"
if [[ "$REGISTER_MSG" == "$MSG_INVALID_CREDENTIALS" ]]; then
    pass "Register Duplicate Email Returns Generic Message"
else
    fail "Register Enumeration Leak: '$REGISTER_MSG'"
fi

# --- 2. Injection-shaped input (no 500) ---
echo "--- 2. Injection-shaped input ---"
for payload in "' OR 1=1--" "'; DROP TABLE users;--" "admin@example.com'--" "%' OR '1'='1" "1;SELECT * FROM users"; do
    code=$(post_json_status "$BASE/auth/login" -d "{\"email\":\"${payload}\",\"password\":\"x\"}")
    expect_not_500 "Login SQLi-shaped email" "$code"
done

code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"' OR 1=1--\",\"email\":\"sqli-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
expect_not_500 "Register SQLi-shaped name" "$code"

code=$(post_json_status "$BASE/auth/login" -d '{"email":"admin@example.com","password":"'"'"' OR 1=1--"}')
expect_not_500 "Login SQLi-shaped password" "$code"

# --- 3. Malformed transport ---
echo "--- 3. Malformed transport ---"
code=$(status_code -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{')
expect_code "Malformed JSON body" "422" "$code"

code=$(status_code -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '')
expect_code "Empty JSON body" "422" "$code"

code=$(status_code -X POST "$BASE/auth/login" -H "Content-Type: text/plain" -d 'email=admin@example.com&password=x')
if [[ "$code" == "422" || "$code" == "415" ]]; then
    pass "Wrong content-type rejected ($code)"
else
    warn "Wrong Content-Type" "HTTP $code"
fi

# --- 4. Rate limiting ---
echo "--- 4. Rate limiting ---"
LIMITED=0
for _ in $(seq 1 15); do
    code=$(post_json_status "$BASE/auth/login" -d '{"email":"brute@example.com","password":"wrong"}')
    if [[ "$code" == "429" ]]; then LIMITED=1; break; fi
done
if [[ "$LIMITED" == "1" ]]; then
    pass "Login rate limit triggered (429)"
else
    warn "Login Rate Limit" "No 429 ($MSG_TOO_MANY_REQUESTS) After 15 Attempts"
fi

REG_LIMITED=0
REG_BRUTE_EMAIL="rate-brute-${RANDOM}@example.com"
for _ in $(seq 1 15); do
    post_json "$BASE/auth/register" -d "{\"name\":\"Rate\",\"email\":\"${REG_BRUTE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
    code="$(json_status)"
    if [[ "$code" == "429" ]]; then REG_LIMITED=1; break; fi
done
if [[ "$REG_LIMITED" == "1" ]]; then
    pass "Register rate limit triggered (429)"
else
    warn "Register Rate Limit" "No 429 ($MSG_TOO_MANY_REQUESTS) After 15 Attempts"
fi

# --- 5. Mass assignment on register ---
echo "--- 5. Mass assignment on register ---"
MASS_EMAIL="hacker-${RANDOM}@example.com"
post_json "$BASE/auth/register" -d "{\"name\":\"Hacker\",\"email\":\"${MASS_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\",\"team_id\":1,\"is_admin\":true,\"email_verified_at\":\"2026-01-01T00:00:00Z\",\"role\":\"Admin\"}"
code="$(json_status)"
if [[ "$code" == "201" ]]; then
    RESULT="$(artisan_tinker "\$u=App\\Models\\User::where('email','${MASS_EMAIL}')->first(); \$ok=\$u && \$u->team_id===null && \$u->email_verified_at===null && \$u->roles->pluck('name')->first()==='User'; echo \$ok ? 'ok' : 'fail';")"
    if [[ "$RESULT" == "ok" ]]; then
        pass "Mass assignment ignored (null team, User role)"
    else
        fail "Mass Assignment May Have Elevated Privileges"
    fi
else
    pass "Register with extra fields rejected or blocked ($code)"
fi

# --- 6. Password policy ---
echo "--- 6. Password policy ---"
code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"Weak\",\"email\":\"weak-${RANDOM}@example.com\",\"password\":\"short\",\"password_confirmation\":\"short\"}")
expect_code "Weak password rejected" "422" "$code"

code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"Mismatch\",\"email\":\"mismatch-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"DifferentPass12\"}")
expect_code "Password confirmation mismatch" "422" "$code"

code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"NoDigits\",\"email\":\"nodigits-${RANDOM}@example.com\",\"password\":\"SecretPassword\",\"password_confirmation\":\"SecretPassword\"}")
expect_code "Password without numbers rejected" "422" "$code"

code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"Breached\",\"email\":\"breached-${RANDOM}@example.com\",\"password\":\"Password123\",\"password_confirmation\":\"Password123\"}")
expect_code "Breached password rejected (uncompromised)" "422" "$code"

# --- 7. Bearer token abuse (deliberately invalid tokens) ---
echo "--- 7. Bearer token abuse ---"
code=$(status_code -X GET "$BASE/users" -H "Accept: application/json" -H "Authorization: Bearer ")
expect_code "Empty bearer token" "401" "$code"

code=$(status_code -X GET "$BASE/users" -H "Accept: application/json" -H "Authorization: Bearer ../../...sswd")
expect_code "Path traversal bearer" "401" "$code"

code=$(status_code -X GET "$BASE/users" -H "Accept: application/json" -H "Authorization: Bearer eyJhbG...e30.")
expect_code "JWT-shaped garbage bearer" "401" "$code"

code=$(status_code -X GET "$BASE/users" -H "Accept: application/json" -H "Authorization: Bearer 1|tota...alue")
expect_code "Malformed Sanctum token" "401" "$code"

# --- 8. Logout boundaries ---
echo "--- 8. Logout boundaries ---"
code=$(post_json_status "$BASE/logout")
expect_code "Logout without token" "401" "$code"

code=$(post_json_status "$BASE/logout" -H "Authorization: Bearer invalid-token-value")
expect_code "Logout invalid bearer" "401" "$code"

# --- 9. Token revocation ---
echo "--- 9. Token revocation ---"
reset_rate_limits
REV_TOKEN="$(login_token admin@example.com password)"
if [[ -z "$REV_TOKEN" ]]; then
    fail "Could Not Obtain Admin Token for Revocation Tests"
else
    curl -s -o "$BODY_FILE" -X POST "$BASE/logout" -H "Accept: application/json" -H "Authorization: Bearer ${REV_TOKEN}"
    code=$(auth_get "$BASE/users" "$REV_TOKEN")
    expect_code "Token invalid after logout" "401" "$code"
fi

# --- 10. Remember-me boundaries ---
echo "--- 10. Remember-me boundaries ---"
code=$(post_json_status "$BASE/auth/login/remember")
expect_code "Remember without session" "401" "$code"

# Fresh cookie jar, then a stateful login with remember:true.
rm -f "$COOKIE_JAR"
COOKIE_JAR="$(mktemp)"
begin_stateful_session
XSRF="$(read_xsrf_token)"
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}" \
    -d '{"email":"manager@example.com","password":"password","remember":true}' -o "$BODY_FILE"

# Restore without the CSRF header must be blocked.
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/auth/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/")
if [[ "$code" == "419" || "$code" == "401" ]]; then
    pass "Remember without CSRF header blocked ($code)"
else
    fail "Remember Without CSRF Allowed ($code)"
fi

# Restore with a fresh CSRF header should succeed.
begin_stateful_session
XSRF="$(read_xsrf_token)"
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/auth/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}")
if [[ "$code" == "200" ]]; then
    pass "Remember restore with cookie and CSRF (200)"
else
    warn "Remember Cookie Flow" "HTTP $code"
fi

# --- 11. Soft-deleted account ---
echo "--- 11. Soft-deleted account ---"
DELETE_EMAIL="deleted-${RANDOM}@example.com"
post_json "$BASE/auth/register" -d "{\"name\":\"Delete Me\",\"email\":\"${DELETE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
artisan_tinker "App\\Models\\User::where('email','${DELETE_EMAIL}')->first()?->delete(); echo 'deleted';"
post_json "$BASE/auth/login" -d "{\"email\":\"${DELETE_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
MSG="$(json_errors_email)"
if [[ "$MSG" == "$MSG_INVALID_CREDENTIALS" ]]; then
    pass "Soft-Deleted User Login Generic Error"
else
    fail "Soft-Delete Leak: '$MSG'"
fi

# --- 12. Authorization boundaries ---
echo "--- 12. Authorization boundaries ---"
reset_rate_limits
MANAGER_TOKEN="$(login_token manager@example.com password)"
ADMIN_TOKEN="$(login_token admin@example.com password)"
USER_EMAIL="userrole-${RANDOM}@example.com"
USER_TOKEN="$(register_and_login_token "$USER_EMAIL" "$STRONG_PASS" "User Role")"

if [[ -z "$MANAGER_TOKEN" || -z "$USER_TOKEN" ]]; then
    fail "Could Not Obtain Tokens for Authorization Boundary Tests"
else
    code=$(auth_get "$BASE/users" "$MANAGER_TOKEN")
    expect_code "Manager can list users" "200" "$code"

    code=$(auth_get "$BASE/users" "$USER_TOKEN")
    expect_code "User role cannot list users" "403" "$code"

    code=$(auth_get "$BASE/roles" "$USER_TOKEN")
    expect_code "User role cannot list roles" "403" "$code"
fi

# --- 13. Token IDOR ---
echo "--- 13. Token IDOR ---"
if [[ -z "${USER_TOKEN:-}" ]]; then
    warn "Token IDOR" "No User Token Available"
else
    code=$(auth_delete "$BASE/tokens/1" "$USER_TOKEN")
    if [[ "$code" == "403" || "$code" == "404" ]]; then
        pass "Cannot delete arbitrary token id ($code)"
    else
        fail "Token IDOR Delete Returned $code"
    fi
fi

# --- 14. Token abilities cannot bypass role policy ---
echo "--- 14. Token ability escalation ---"
if [[ -z "${USER_TOKEN:-}" ]]; then
    warn "Token Ability Escalation" "No User Token Available"
else
    auth_post "$BASE/tokens" "$USER_TOKEN" \
        -d '{"name":"Escalation Attempt","abilities":["users.list","users.delete","*"]}'
    code="$(json_status)"
    if [[ "$code" == "201" ]]; then
        ESC_TOKEN="$(json_path 'data.plain_text_token')"
        code=$(auth_get "$BASE/users" "$ESC_TOKEN")
        expect_code "Escalated token still denied users.list" "403" "$code"
    else
        pass "Token creation with hostile abilities blocked ($code)"
    fi
fi

# --- 15. XSS / markup sanitisation ---
echo "--- 15. XSS / markup sanitisation ---"
post_json "$BASE/auth/register" -d "{\"name\":\"<script>alert(1)</script>Bob\",\"email\":\"xss-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
NAME="$(json_path 'data.user.name')"
if [[ "$NAME" != *"<script>"* ]]; then
    pass "Register name strips script tags ($NAME)"
else
    fail "XSS in Register Name Persisted"
fi

post_json "$BASE/auth/login" -d '{"email":"admin@example.com","password":"password","device_name":"<img src=x onerror=alert(1)>CLI"}'
TOKEN_NAME="$(json_path 'data.token.name')"
if [[ "$TOKEN_NAME" != *"<img"* ]]; then
    pass "Login device_name strips markup ($TOKEN_NAME)"
else
    fail "XSS in Login device_name Persisted"
fi

# --- 16. Oversized input ---
echo "--- 16. Oversized input ---"
LONG="$(python3 -c "print('a'*10000)")"
code=$(post_json_status "$BASE/auth/login" -d "{\"email\":\"${LONG}@example.com\",\"password\":\"x\"}")
if [[ "$code" == "422" || "$code" == "413" ]]; then
    pass "Oversized email rejected ($code)"
else
    warn "Oversized Email" "HTTP $code"
fi

code=$(post_json_status "$BASE/auth/register" -d "{\"name\":\"${LONG}\",\"email\":\"longname-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
if [[ "$code" == "422" || "$code" == "413" ]]; then
    pass "Oversized name rejected ($code)"
else
    warn "Oversized Name" "HTTP $code"
fi

# --- 17. HTTP verb tampering ---
echo "--- 17. HTTP verb tampering ---"
code=$(status_code -X GET "$BASE/auth/login")
expect_code "GET /login" "405" "$code"

code=$(status_code -X PUT "$BASE/auth/register" -H "Content-Type: application/json" -d "{\"name\":\"X\",\"email\":\"x@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
expect_code "PUT /register" "405" "$code"

if [[ -n "${ADMIN_TOKEN:-}" ]]; then
    code=$(auth_delete "$BASE/logout" "$ADMIN_TOKEN")
    if [[ "$code" == "405" || "$code" == "401" ]]; then
        pass "DELETE /logout blocked ($code)"
    else
        fail "DELETE /logout Returned $code"
    fi
else
    warn "DELETE /logout" "No Admin Token Available"
fi

# --- 18. Audit trail ---
echo "--- 18. Audit trail ---"
drain_audit_queue
COUNT="$(artisan_tinker "echo App\\Models\\AuthAuditLog::where('event','Login Failed')->count();")"
if [[ "${COUNT:-0}" =~ ^[0-9]+$ && "${COUNT:-0}" -gt 0 ]]; then
    pass "Failed logins recorded in audit ($COUNT rows)"
else
    fail "No Failed Login Audit Rows After Queue Drain: got '$COUNT'"
fi

# --- 19. Email normalisation ---
echo "--- 19. Email normalisation ---"
reset_rate_limits
CASE_EMAIL="CASE-${RANDOM}@EXAMPLE.COM"
post_json "$BASE/auth/register" -d "{\"name\":\"Case\",\"email\":\"${CASE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
LOWER="$(echo "$CASE_EMAIL" | tr '[:upper:]' '[:lower:]')"
EMAIL="$(artisan_tinker "echo App\\Models\\User::where('email','${LOWER}')->value('email') ?? '';")"
if [[ "$EMAIL" == "$LOWER" ]]; then
    pass "Register lowercases email ($EMAIL)"
else
    fail "Email Case Not Normalised: $EMAIL"
fi

post_json "$BASE/auth/register" -d "{\"name\":\"Dup\",\"email\":\"${LOWER}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
expect_code "Duplicate email after normalisation" "422" "$(json_status)"

post_json "$BASE/auth/login" -d '{"email":"ADMIN@EXAMPLE.COM","password":"password"}'
if [[ "$(json_status)" == "200" ]]; then
    pass "Login accepts case-variant email for known account"
else
    fail "Login Case-Variant Email Failed ($(json_status))"
fi

# --- 20. Logout kills all sessions ---
echo "--- 20. Logout kills all sessions ---"
reset_rate_limits
T1="$(login_token admin@example.com password)"
T2="$(login_token admin@example.com password)"
if [[ -z "$T1" || -z "$T2" ]]; then
    fail "Could Not Obtain Two Admin Tokens for Logout-All Test"
else
    curl -s -o "$BODY_FILE" -X POST "$BASE/logout" -H "Accept: application/json" -H "Authorization: Bearer ${T1}"
    c1=$(auth_get "$BASE/users" "$T1")
    c2=$(auth_get "$BASE/users" "$T2")
    if [[ "$c1" == "401" && "$c2" == "401" ]]; then
        pass "Logout revokes ALL tokens"
    else
        fail "Token Leak After Logout: t1=$c1 t2=$c2"
    fi
fi

# --- 21. Response leakage ---
echo "--- 21. Response leakage ---"
LOGIN_JSON="$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"password"}')"
HAS_PW="$(echo "$LOGIN_JSON" | python3 -c "import json,sys; d=json.load(sys.stdin); print('password' in str(d).lower())" 2>/dev/null || echo "False")"
if [[ "$HAS_PW" == "False" ]]; then
    pass "No password field in login response"
else
    fail "Password Material in Login Response"
fi

# --- 22. Admin force-logout ---
echo "--- 22. Admin force-logout ---"
reset_rate_limits
ADMIN_TOKEN="$(issue_token admin@example.com)"
MANAGER_TOKEN="$(issue_token manager@example.com)"
FORCE_EMAIL="force-target-${RANDOM}@example.com"
FORCE_TOKEN="$(register_and_login_token "$FORCE_EMAIL" "$STRONG_PASS" "Force Target")"
TARGET_ID="$(artisan_tinker "echo App\\Models\\User::where('email','${FORCE_EMAIL}')->value('id');")"

if [[ -z "$ADMIN_TOKEN" || -z "$MANAGER_TOKEN" || -z "$FORCE_TOKEN" ]]; then
    fail "Could Not Issue Tokens for Force-Logout Tests"
else
    code=$(auth_post "$BASE/users/logout" "$FORCE_TOKEN" -d "{\"ids\":[${TARGET_ID}]}")
    expect_code "User cannot force-logout others" "403" "$code"

    code=$(auth_post "$BASE/users/logout" "$MANAGER_TOKEN" -d "{\"ids\":[${TARGET_ID}]}")
    expect_code "Manager cannot force-logout" "403" "$code"

    if [[ -n "$TARGET_ID" && "$TARGET_ID" =~ ^[0-9]+$ ]]; then
        code=$(auth_post "$BASE/users/logout" "$ADMIN_TOKEN" -d "{\"ids\":[${TARGET_ID}]}")
        expect_code "Admin can force-logout target user" "200" "$code"
        code=$(auth_get "$BASE/tokens" "$FORCE_TOKEN")
        expect_code "Force-logout revokes victim tokens" "401" "$code"
    else
        warn "Admin Force-Logout" "Could Not Resolve Target User ID"
    fi

    code=$(auth_post "$BASE/users/logout" "$ADMIN_TOKEN" -d '{"ids":[999999]}')
    expect_code "Force-logout unknown id" "422" "$code"
fi

# --- 23. Timing side-channel (rough) ---
echo "--- 23. Timing side-channel (rough) ---"
T_UNKNOWN="$(curl -s -o /dev/null -w '%{time_total}' -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d "{\"email\":\"nonexistent999@example.com\",\"password\":\"${STRONG_PASS}\"}")"
T_WRONG="$(curl -s -o /dev/null -w '%{time_total}' -X POST "$BASE/auth/login" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"WrongPass1"}')"
python3 - <<PY
u,f=float("$T_UNKNOWN"),float("$T_WRONG")
ratio=max(u,f)/min(u,f) if min(u,f)>0 else 1
print(f"INFO  Timing ratio unknown/wrong: {ratio:.2f}x (u={u:.3f}s w={f:.3f}s)")
if ratio > 3:
    print("WARN  Timing side-channel — ratio > 3x may aid enumeration")
else:
    print("PASS  Timing difference not dramatic")
PY

# --- 24. Suspended accounts ---
echo "--- 24. Suspended accounts ---"
reset_rate_limits
SUSPEND_EMAIL="suspended-${RANDOM}@example.com"
SUSPEND_TOKEN="$(register_and_login_token "$SUSPEND_EMAIL" "$STRONG_PASS" "Suspended User")"
suspend_user "$SUSPEND_EMAIL"

post_json "$BASE/auth/login" -d "{\"email\":\"${SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
MSG="$(json_errors_email)"
if [[ "$MSG" == "$MSG_INVALID_CREDENTIALS" ]]; then
    pass "Suspended User Login Returns Generic $MSG_INVALID_CREDENTIALS"
else
    fail "Suspended Login Leak or Success: status=$(json_status) msg='$MSG'"
fi

if [[ -n "$SUSPEND_TOKEN" ]]; then
    code=$(auth_get "$BASE/me" "$SUSPEND_TOKEN")
    expect_code "Suspended bearer token rejected" "403" "$code"

    curl -s -o "$BODY_FILE" -X GET "$BASE/me" \
        -H "Accept: application/json" -H "Authorization: Bearer ${SUSPEND_TOKEN}"
    if [[ "$(json_message)" == "$MSG_ACCOUNT_SUSPENDED" ]]; then
        pass "Suspended Response Message Is $MSG_ACCOUNT_SUSPENDED"
    else
        warn "Suspended Message" "'$(json_message)'"
    fi
else
    warn "Suspended Token Probe" "No Token Before Suspension"
fi

# --- 25. New read endpoints ---
echo "--- 25. New read endpoints ---"
reset_rate_limits
ADMIN_TOKEN="$(login_token admin@example.com password)"
MANAGER_TOKEN="$(login_token manager@example.com password)"
USER_TOKEN="$(login_token test@example.com password)"

if [[ -z "$ADMIN_TOKEN" || -z "$MANAGER_TOKEN" || -z "$USER_TOKEN" ]]; then
    fail "Could Not Obtain Tokens for New Endpoint Probes"
else
    code=$(auth_get "$BASE/audit-logs" "$ADMIN_TOKEN")
    expect_code "Admin can list audit logs" "200" "$code"

    code=$(auth_get "$BASE/audit-logs" "$MANAGER_TOKEN")
    expect_code "Manager cannot list audit logs" "403" "$code"

    code=$(auth_get "$BASE/audit-logs" "$USER_TOKEN")
    expect_code "User cannot list audit logs" "403" "$code"

    AUDIT_ID="$(artisan_tinker "echo App\\Models\\AuthAuditLog::query()->orderBy('id')->value('id') ?? '';")"
    if [[ -n "$AUDIT_ID" && "$AUDIT_ID" =~ ^[0-9]+$ ]]; then
        code=$(auth_get "$BASE/audit-logs/${AUDIT_ID}" "$USER_TOKEN")
        expect_code "User cannot show audit log by id" "403" "$code"
    else
        warn "Audit Log Show IDOR" "No Audit Row ID"
    fi

    code=$(auth_get "$BASE/permissions" "$USER_TOKEN")
    expect_code "User can list permissions catalog" "200" "$code"

    code=$(auth_get "$BASE/permissions" "$MANAGER_TOKEN")
    expect_code "Manager can list permissions catalog" "200" "$code"

    code=$(auth_get "$BASE/clients/1" "$ADMIN_TOKEN")
    expect_code "Admin can show API client" "200" "$code"

    code=$(auth_get "$BASE/clients/1" "$MANAGER_TOKEN")
    expect_code "Manager cannot show API client" "403" "$code"

    SERVICE_TOKEN="$(oauth_token '{"grant_type":"client_credentials","client_id":"demo-integration-client","client_secret":"DemoClientSecret12"}')"
    if [[ -n "$SERVICE_TOKEN" ]]; then
        code=$(auth_get "$BASE/permissions" "$SERVICE_TOKEN")
        expect_code "Service token cannot list permissions" "403" "$code"

        code=$(auth_get "$BASE/audit-logs" "$SERVICE_TOKEN")
        expect_code "Service token cannot list audit logs" "403" "$code"

        code=$(auth_get "$BASE/me" "$SERVICE_TOKEN")
        expect_code "Service token cannot call GET /me" "403" "$code"
    else
        warn "Service Token Probes" "Could Not Exchange Demo Client Credentials"
    fi
fi

# --- 26. OAuth client-credentials abuse ---
echo "--- 26. OAuth client-credentials abuse ---"
reset_rate_limits
post_json "$BASE/oauth/token" -d '{"grant_type":"client_credentials","client_id":"demo-integration-client","client_secret":"WrongSecret12"}'
OAUTH_MSG="$(python3 -c "
import json
try:
    d=json.load(open('$BODY_FILE'))
    errs=d.get('meta',{}).get('errors',{}).get('client_id',[])
    print(errs[0] if errs else '')
except Exception:
    print('')
" 2>/dev/null || echo "")"
if [[ "$OAUTH_MSG" == "$MSG_INVALID_CREDENTIALS" ]]; then
    pass "OAuth Wrong Secret Returns Generic $MSG_INVALID_CREDENTIALS"
else
    fail "OAuth Enumeration Leak: '$OAUTH_MSG'"
fi

post_json "$BASE/oauth/token" -d '{"grant_type":"client_credentials","client_id":"missing-client-id","client_secret":"x"}'
UNKNOWN_OAUTH="$(python3 -c "
import json
try:
    d=json.load(open('$BODY_FILE'))
    errs=d.get('meta',{}).get('errors',{}).get('client_id',[])
    print(errs[0] if errs else '')
except Exception:
    print('')
" 2>/dev/null || echo "")"
if [[ "$UNKNOWN_OAUTH" == "$MSG_INVALID_CREDENTIALS" && "$UNKNOWN_OAUTH" == "$OAUTH_MSG" ]]; then
    pass "OAuth Unknown Client Matches Wrong Secret Message"
else
    fail "OAuth client_id Enumeration: unknown='$UNKNOWN_OAUTH' wrong='$OAUTH_MSG'"
fi

SUSPENDED_CLIENT_SECRET="SuspendedOAuth12"
SUSPENDED_CLIENT_ID="$(artisan_tinker "
\$plain='${SUSPENDED_CLIENT_SECRET}';
\$client=App\\Models\\ApiClient::factory()->create(['client_secret'=>Illuminate\\Support\\Facades\\Hash::make(\$plain)]);
\$client->user->forceFill(['suspended_at'=>now()])->save();
echo \$client->client_id;
")"
if [[ -n "$SUSPENDED_CLIENT_ID" ]]; then
    reset_rate_limits
    post_json "$BASE/oauth/token" -d "{\"grant_type\":\"client_credentials\",\"client_id\":\"${SUSPENDED_CLIENT_ID}\",\"client_secret\":\"${SUSPENDED_CLIENT_SECRET}\"}"
    if [[ "$(json_status)" == "422" ]]; then
        pass "Suspended service user cannot exchange OAuth token (422)"
    else
        fail "Suspended Service User OAuth Returned $(json_status)"
    fi
else
    warn "Suspended OAuth Client" "Could Not Create Suspended Client"
fi

OAUTH_LIMITED=0
for _ in $(seq 1 15); do
    code=$(post_json_status "$BASE/oauth/token" -d '{"grant_type":"client_credentials","client_id":"demo-integration-client","client_secret":"wrong"}')
    if [[ "$code" == "429" ]]; then OAUTH_LIMITED=1; break; fi
done
if [[ "$OAUTH_LIMITED" == "1" ]]; then
    pass "OAuth token rate limit triggered (429)"
else
    warn "OAuth Rate Limit" "No 429 ($MSG_TOO_MANY_REQUESTS) After 15 Bad Attempts"
fi

# --- 27. Remember-me + suspension ---
echo "--- 27. Remember-me + suspension ---"
reset_rate_limits
REMEMBER_SUSPEND_EMAIL="remember-suspend-${RANDOM}@example.com"
register_and_login_token "$REMEMBER_SUSPEND_EMAIL" "$STRONG_PASS" "Remember Suspend" > /dev/null

if stateful_sessions_supported; then
rm -f "$COOKIE_JAR"
COOKIE_JAR="$(mktemp)"
begin_stateful_session
XSRF="$(read_xsrf_token)"
    curl --globoff -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/auth/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}" \
    -d "{\"email\":\"${REMEMBER_SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"remember\":true}" -o "$BODY_FILE"

    SUSPEND_RESULT="$(suspend_user "$REMEMBER_SUSPEND_EMAIL")"
    if [[ "$SUSPEND_RESULT" != "suspended" ]]; then
        fail "Could Not Suspend User for Remember-Me Probe: got '${SUSPEND_RESULT}'"
    fi

begin_stateful_session
XSRF="$(read_xsrf_token)"
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/auth/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}")
if [[ "$code" == "401" ]]; then
    pass "Remember-me blocked for suspended user (401)"
else
        fail "Remember-Me Allowed for Suspended User ($code)"
    fi
else
    warn "Remember-Me + Suspension" "SESSION_DRIVER=array — Cookie Restore Not Exercised"

    SUSPEND_RESULT="$(suspend_user "$REMEMBER_SUSPEND_EMAIL")"
    if [[ "$SUSPEND_RESULT" != "suspended" ]]; then
        fail "Could Not Suspend User for Login Probe: got '${SUSPEND_RESULT}'"
    fi
fi

post_json "$BASE/auth/login" -d "{\"email\":\"${REMEMBER_SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
LOGIN_STATUS="$(json_status)"
TFA_REQUIRED="$(json_path 'data.two_factor_required' | tr '[:upper:]' '[:lower:]')"
if [[ "$LOGIN_STATUS" == "200" && "$TFA_REQUIRED" == "true" ]]; then
    fail "Suspended User Reached MFA Challenge After Remember (Credentials Not Blocked)"
elif [[ "$(json_errors_email)" == "$MSG_INVALID_CREDENTIALS" || "$LOGIN_STATUS" != "200" ]]; then
    pass "Suspended user cannot complete sign-in after remember cookie (${LOGIN_STATUS})"
else
    fail "Suspended User Password Login After Remember Succeeded (${LOGIN_STATUS})"
fi

# --- 28. Permission catalog query hardening ---
echo "--- 28. Permission catalog query hardening ---"
if [[ -n "${ADMIN_TOKEN:-}" ]]; then
    code=$(auth_get "$BASE/permissions?include=user" "$ADMIN_TOKEN")
    if [[ "$code" == "422" ]]; then
        pass "Permissions index rejects include=user (422)"
    else
        fail "Permissions Index Allowed Hostile Include ($code)"
    fi

    code=$(auth_get "$BASE/permissions?sort=created_at" "$ADMIN_TOKEN")
    if [[ "$code" == "422" ]]; then
        pass "Permissions index rejects unsupported sort (422)"
    else
        fail "Permissions Index Allowed Unsupported Sort ($code)"
    fi
else
    warn "Permission Catalog Hardening" "No Admin Token"
fi

# --- 29. Retired flat auth paths (must not linger) ---
echo "--- 29. Retired flat auth paths ---"
for legacy in \
    "$BASE/login" \
    "$BASE/register" \
    "$BASE/login/remember" \
    "$BASE/two-factor/send" \
    "$BASE/two-factor/verify" \
    "$BASE/two-factor/status"; do
    code=$(post_json_status "$legacy" -d '{"email":"admin@example.com","password":"password"}')
    if [[ "$code" == "404" || "$code" == "405" ]]; then
        pass "Legacy path gone: ${legacy#$BASE} ($code)"
    else
        fail "Legacy Path Still Reachable: ${legacy#$BASE} ($code)"
    fi
done

code=$(status_code -X GET "$BASE/two-factor/status")
expect_code "Legacy GET /two-factor/status" "404" "$code"

# --- 30. Web session registry boundaries ---
echo "--- 30. Web session registry boundaries ---"
reset_rate_limits
code=$(status_code -X GET "$BASE/sessions" -H "Accept: application/json")
expect_code "Sessions index without token" "401" "$code"

USER_TOKEN="$(login_token test@example.com password)"
ADMIN_TOKEN="$(login_token admin@example.com password)"
SERVICE_TOKEN="$(oauth_token '{"grant_type":"client_credentials","client_id":"demo-integration-client","client_secret":"DemoClientSecret12"}')"

if [[ -z "$USER_TOKEN" || -z "$ADMIN_TOKEN" ]]; then
    fail "Could Not Obtain Tokens for Web Session Probes"
else
    code=$(auth_get "$BASE/sessions" "$USER_TOKEN")
    expect_code "User can list own web sessions" "200" "$code"

    code=$(auth_get "$BASE/sessions" "$ADMIN_TOKEN")
    expect_code "Admin can list web sessions" "200" "$code"

    if [[ -n "$SERVICE_TOKEN" ]]; then
        code=$(auth_get "$BASE/sessions" "$SERVICE_TOKEN")
        expect_code "Service token cannot list web sessions" "403" "$code"
    else
        warn "Service Session List" "No Service Token"
    fi

    code=$(status_code -X GET "$BASE/sessions?filter[user_id]=1" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${USER_TOKEN}")
    expect_code "User cannot filter sessions by user_id" "422" "$code"

    code=$(status_code -X GET "$BASE/sessions?filter%5Buser_id%5D=1" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${ADMIN_TOKEN}")
    if [[ "$code" == "200" ]]; then
        pass "Admin may filter sessions by user_id (200)"
    else
        fail "Admin filter[user_id] Blocked ($code)"
    fi

    for payload in "' OR 1=1--" "1;DROP TABLE web_sessions;--" "%' OR '1'='1"; do
        code=$(status_code -G "$BASE/sessions" \
            --data-urlencode "filter[search]=${payload}" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${USER_TOKEN}")
        expect_not_500 "Sessions search SQLi-shaped" "$code"
    done

    code=$(status_code -G "$BASE/sessions" \
        --data-urlencode "filter[user_id]=999999999999999999999" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${ADMIN_TOKEN}")
    expect_not_500 "Sessions absurd user_id filter" "$code"
fi

# --- 31. Web session IDOR + enumeration hardening ---
echo "--- 31. Web session IDOR ---"
reset_rate_limits
VICTIM_EMAIL="session-victim-${RANDOM}@example.com"
ATTACKER_EMAIL="session-attacker-${RANDOM}@example.com"

VICTIM_TOKEN="$(register_and_login_token "$VICTIM_EMAIL" "$STRONG_PASS" "Victim")"
ATTACKER_TOKEN="$(register_and_login_token "$ATTACKER_EMAIL" "$STRONG_PASS" "Attacker")"

# Bearer-only login should not register a cookie session row.
VICTIM_BEARER_ONLY_BEFORE="$(artisan_tinker "echo App\\Models\\WebSession::where('user_id', App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${VICTIM_EMAIL}'))->value('id'))->count();")"
if [[ "${VICTIM_BEARER_ONLY_BEFORE:-x}" == "0" ]]; then
    pass "Bearer-only login does not create web session rows"
else
    fail "Bearer-Only Login Registered ${VICTIM_BEARER_ONLY_BEFORE} Web Session Row(s)"
fi

ensure_web_session_row "$VICTIM_EMAIL" "$STRONG_PASS" true > /dev/null
VICTIM_SESSION_ID="$(web_session_id_for_user "$VICTIM_EMAIL")"

if [[ -z "$VICTIM_SESSION_ID" ]]; then
    fail "Could Not Create Web Session Row for IDOR Tests"
else
    pass "Web session registry row present (id ${VICTIM_SESSION_ID})"

    if [[ -n "$ATTACKER_TOKEN" ]]; then
        code=$(auth_delete "$BASE/sessions/${VICTIM_SESSION_ID}" "$ATTACKER_TOKEN")
        if [[ "$code" == "404" ]]; then
            pass "Foreign session revoke returns 404 (not 403)"
        else
            fail "Session IDOR Revoke Returned $code (Want 404)"
        fi

        curl -s -o "$BODY_FILE" -X GET "$BASE/sessions" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${ATTACKER_TOKEN}"
        ATTACKER_SEES="$(json_data_count)"
        if [[ "${ATTACKER_SEES:-0}" == "0" ]]; then
            pass "Attacker session index scoped to own rows (0)"
        else
            fail "Attacker Saw ${ATTACKER_SEES} Foreign Session Row(s)"
        fi
    fi

    # Route binding must not treat the literal "current" as a numeric id.
    code=$(auth_delete "$BASE/sessions/current" "$VICTIM_TOKEN")
    if [[ "$code" == "404" || "$code" == "405" || "$code" == "401" ]]; then
        pass "DELETE /sessions/current without cookie session blocked ($code)"
    else
        fail "Bearer-Only DELETE /sessions/current Returned $code"
    fi
fi

# --- 32. Surgical session revoke vs global logout ---
echo "--- 32. Surgical session revoke vs global logout ---"
reset_rate_limits
SURGICAL_EMAIL="session-surgical-${RANDOM}@example.com"

T_SURGICAL_A="$(register_and_login_token "$SURGICAL_EMAIL" "$STRONG_PASS" "Surgical")"
T_SURGICAL_B="$(issue_token "$SURGICAL_EMAIL")"
ensure_web_session_row "$SURGICAL_EMAIL" "$STRONG_PASS" true > /dev/null
SURGICAL_SESSION_ID="$(web_session_id_for_user "$SURGICAL_EMAIL")"

if [[ -z "$T_SURGICAL_A" || -z "$SURGICAL_SESSION_ID" ]]; then
    fail "Could Not Set Up Surgical Revoke Scenario"
else
    code=$(auth_delete "$BASE/sessions/${SURGICAL_SESSION_ID}" "$T_SURGICAL_A")
    expect_code "Surgical session revoke" "200" "$code"

    REVOKED_COUNT="$(revoked_web_session_count_for_user "$SURGICAL_EMAIL")"
    if [[ "${REVOKED_COUNT:-0}" -ge 1 ]]; then
        pass "Registry row marked revoked (${REVOKED_COUNT})"
    else
        fail "Registry Row Not Revoked After DELETE /sessions/{id}"
    fi

    code=$(auth_get "$BASE/me" "$T_SURGICAL_A")
    if [[ "$code" == "200" ]]; then
        pass "Surgical revoke left bearer token A valid (200)"
    else
        fail "Surgical Revoke Killed Bearer Token A ($code)"
    fi

    if [[ -n "$T_SURGICAL_B" ]]; then
        code=$(auth_get "$BASE/me" "$T_SURGICAL_B")
        if [[ "$code" == "200" ]]; then
            pass "Surgical revoke left bearer token B valid (200)"
        else
            fail "Surgical Revoke Killed Bearer Token B ($code)"
        fi
    fi
fi

# Global logout must revoke registry rows AND bearer tokens.
GLOBAL_EMAIL="session-global-${RANDOM}@example.com"
G1="$(register_and_login_token "$GLOBAL_EMAIL" "$STRONG_PASS" "Global")"
G2="$(issue_token "$GLOBAL_EMAIL")"
ensure_web_session_row "$GLOBAL_EMAIL" "$STRONG_PASS" true > /dev/null

BEFORE_GLOBAL="$(active_web_session_count)"
if [[ "${BEFORE_GLOBAL:-0}" -gt 0 ]]; then
    pass "Registry has active rows before global logout (${BEFORE_GLOBAL})"
else
    warn "Global Logout Registry" "No Active Rows Before Logout"
fi

if [[ -n "$G1" ]]; then
    curl -s -o "$BODY_FILE" -X POST "$BASE/logout" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${G1}"
    code=$(auth_get "$BASE/me" "$G1")
    expect_code "Global logout revokes bearer G1" "401" "$code"
    if [[ -n "$G2" ]]; then
        code=$(auth_get "$BASE/me" "$G2")
        expect_code "Global logout revokes bearer G2" "401" "$code"
    fi
    REVOKED_GLOBAL="$(revoked_web_session_count_for_user "$GLOBAL_EMAIL")"
    if [[ "${REVOKED_GLOBAL:-0}" -ge 1 ]]; then
        pass "Global logout revoked registry rows (${REVOKED_GLOBAL})"
    else
        fail "Global Logout Left Registry Rows Active"
    fi
else
    warn "Global Logout Token Probe" "No Token"
fi

# --- 33. Admin cross-user session revoke ---
echo "--- 33. Admin cross-user session revoke ---"
reset_rate_limits
ADMIN_CROSS_TOKEN="$(login_token admin@example.com password)"
ADMIN_VICTIM_EMAIL="session-admin-victim-${RANDOM}@example.com"
register_and_login_token "$ADMIN_VICTIM_EMAIL" "$STRONG_PASS" "Admin Victim" > /dev/null
ensure_web_session_row "$ADMIN_VICTIM_EMAIL" "$STRONG_PASS" true > /dev/null
TARGET_SESSION_ID="$(web_session_id_for_user "$ADMIN_VICTIM_EMAIL")"

if [[ -z "$ADMIN_CROSS_TOKEN" || -z "$TARGET_SESSION_ID" ]]; then
    fail "Admin Cross-User Revoke Setup Failed"
else
    code=$(auth_delete "$BASE/sessions/${TARGET_SESSION_ID}" "$ADMIN_CROSS_TOKEN")
    expect_code "Admin revoke foreign active session" "200" "$code"
fi

# --- 34. Session list scope for admin vs user ---
echo "--- 34. Session list scope ---"
reset_rate_limits
SCOPE_A="scope-a-${RANDOM}@example.com"
SCOPE_B="scope-b-${RANDOM}@example.com"
register_and_login_token "$SCOPE_A" "$STRONG_PASS" "Scope A" > /dev/null
register_and_login_token "$SCOPE_B" "$STRONG_PASS" "Scope B" > /dev/null
ensure_web_session_row "$SCOPE_A" "$STRONG_PASS" true > /dev/null
ensure_web_session_row "$SCOPE_B" "$STRONG_PASS" true > /dev/null

ADMIN_SCOPE_TOKEN="$(login_token admin@example.com password)"
USER_SCOPE_TOKEN="$(issue_token "$SCOPE_A")"

if [[ -n "$ADMIN_SCOPE_TOKEN" ]]; then
    curl -s -o "$BODY_FILE" -X GET "$BASE/sessions" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${ADMIN_SCOPE_TOKEN}"
    ADMIN_TOTAL="$(json_data_count)"
    if [[ "${ADMIN_TOTAL:-0}" -ge 2 ]]; then
        pass "Admin session index spans users (${ADMIN_TOTAL} rows)"
    else
        warn "Admin Session Scope" "Only ${ADMIN_TOTAL} Row(s) Visible"
    fi
fi

if [[ -n "$USER_SCOPE_TOKEN" ]]; then
    curl -s -o "$BODY_FILE" -X GET "$BASE/sessions" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${USER_SCOPE_TOKEN}"
    USER_TOTAL="$(json_data_count)"
    if [[ "${USER_TOTAL:-0}" -le 1 ]]; then
        pass "User session index scoped to self (${USER_TOTAL} row(s))"
    else
        fail "User Saw ${USER_TOTAL} Session Rows (Expected <=1)"
    fi
fi

# --- 35. Stale session_version after force-logout ---
echo "--- 35. Stale session_version gate ---"
reset_rate_limits
STALE_EMAIL="session-stale-${RANDOM}@example.com"
STALE_TOKEN="$(register_and_login_token "$STALE_EMAIL" "$STRONG_PASS" "Stale")"
STALE_USER_ID="$(artisan_tinker "echo App\\Models\\User::where('email', Illuminate\\Support\\Str::lower('${STALE_EMAIL}'))->value('id');")"
ADMIN_STALE_TOKEN="$(login_token admin@example.com password)"

if [[ -z "$STALE_TOKEN" || -z "$STALE_USER_ID" || -z "$ADMIN_STALE_TOKEN" ]]; then
    warn "Stale session_version" "Missing Token or User ID"
else
    if stateful_sessions_supported; then
        stateful_login "$STALE_EMAIL" "$STRONG_PASS" true > /dev/null
    fi

    auth_post "$BASE/users/logout" "$ADMIN_STALE_TOKEN" -d "{\"ids\":[${STALE_USER_ID}]}"

    if stateful_sessions_supported; then
        code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X GET "$BASE/me" \
            -H "Accept: application/json" \
            -H "Origin: http://localhost" \
            -H "Referer: http://localhost/" \
            -H "Authorization: Bearer ${STALE_TOKEN}")
        if [[ "$code" == "401" ]]; then
            pass "Stale cookie session rejected after force-logout (401)"
        else
            fail "Stale Cookie Session Still Valid After Force-Logout ($code)"
        fi
    else
        warn "Stale Cookie session_version" "SESSION_DRIVER=array — Cookie Gate Not Exercised"
    fi

    code=$(auth_get "$BASE/me" "$STALE_TOKEN")
    expect_code "Force-logout revoked bearer for stale user" "401" "$code"
fi

# --- 36. Session registry hardening (double revoke, forged ids, verbs) ---
echo "--- 36. Session registry hardening ---"
reset_rate_limits
HARDEN_EMAIL="session-harden-${RANDOM}@example.com"
HARDEN_TOKEN="$(register_and_login_token "$HARDEN_EMAIL" "$STRONG_PASS" "Harden")"
HARDEN_SESSION_ID="$(ensure_web_session_row "$HARDEN_EMAIL" "$STRONG_PASS" true)"

if [[ -z "$HARDEN_TOKEN" || -z "$HARDEN_SESSION_ID" ]]; then
    fail "Session Hardening Setup Failed"
else
    code=$(auth_delete "$BASE/sessions/${HARDEN_SESSION_ID}" "$HARDEN_TOKEN")
    expect_code "First surgical revoke" "200" "$code"

    code=$(auth_delete "$BASE/sessions/${HARDEN_SESSION_ID}" "$HARDEN_TOKEN")
    if [[ "$code" == "404" ]]; then
        pass "Double revoke returns 404"
    elif [[ "$code" == "200" ]]; then
        pass "Double revoke is idempotent (200)"
    else
        fail "Double Revoke Returned $code (Want 404 or Idempotent 200)"
    fi

    for forged in "0" "-1" "999999999" "current" "../1" "1%00"; do
        code=$(auth_delete "$BASE/sessions/${forged}" "$HARDEN_TOKEN")
        if [[ "$code" == "404" || "$code" == "405" || "$code" == "422" ]]; then
            pass "Forged session id blocked: ${forged} ($code)"
        else
            fail "Forged Session ID ${forged} Returned $code"
        fi
    done

    code=$(status_code -X POST "$BASE/sessions" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${HARDEN_TOKEN}" \
        -d '{"session_id":"evil"}')
    if [[ "$code" == "405" || "$code" == "404" ]]; then
        pass "POST /sessions not allowed ($code)"
    else
        fail "POST /sessions Returned $code"
    fi

    code=$(status_code -X DELETE "$BASE/sessions" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${HARDEN_TOKEN}")
    if [[ "$code" == "405" || "$code" == "404" ]]; then
        pass "DELETE /sessions collection blocked ($code)"
    else
        fail "DELETE /sessions Collection Returned $code"
    fi
fi

# --- 37. Two-factor token isolation ---
echo "--- 37. Two-factor token isolation ---"
reset_rate_limits
TFA_A="tfa-a-${RANDOM}@example.com"
TFA_B="tfa-b-${RANDOM}@example.com"
post_json "$BASE/auth/register" -d "{\"name\":\"TFA A\",\"email\":\"${TFA_A}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
TOKEN_A="$(json_path 'data.two_factor_token')"
post_json "$BASE/auth/register" -d "{\"name\":\"TFA B\",\"email\":\"${TFA_B}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
TOKEN_B="$(json_path 'data.two_factor_token')"

if [[ -z "$TOKEN_A" || -z "$TOKEN_B" ]]; then
    fail "Two-Factor Isolation Setup Failed"
else
    post_json "$BASE/auth/two-factor/send" -d "{\"channel\":\"email\",\"two_factor_token\":\"${TOKEN_A}\"}"
    inject_two_factor_code "$TFA_A" "111111" > /dev/null

    curl --globoff -s -o "$BODY_FILE" -X POST "$BASE/auth/two-factor/verify" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{\"code\":\"111111\",\"two_factor_token\":\"${TOKEN_B}\"}"
    if [[ "$(json_status)" == "422" || "$(json_status)" == "401" ]]; then
        pass "Cross-user two_factor_token rejected ($(json_status))"
    else
        fail "Cross-User two_factor_token Verify Returned $(json_status)"
    fi

    curl --globoff -s -o "$BODY_FILE" -X POST "$BASE/auth/two-factor/verify" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "{\"code\":\"000000\",\"two_factor_token\":\"${TOKEN_A}\"}"
    if [[ "$(json_status)" == "422" ]]; then
        pass "Wrong 2FA code rejected (422)"
    else
        fail "Wrong 2FA Code Returned $(json_status)"
    fi
fi

# --- 38. Session include / sort injection ---
echo "--- 38. Session query injection ---"
reset_rate_limits
QUERY_TOKEN="$(login_token test@example.com password)"
if [[ -z "$QUERY_TOKEN" ]]; then
    warn "Session Query Injection" "No User Token"
else
    for payload in "user" "user.password" "permissions" "'; DROP TABLE web_sessions;--"; do
        code=$(status_code -G "$BASE/sessions" \
            --data-urlencode "include=${payload}" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${QUERY_TOKEN}")
        if [[ "$code" == "422" || "$code" == "200" ]]; then
            pass "Sessions include=${payload} handled ($code)"
        else
            fail "Sessions include=${payload} Returned $code"
        fi
    done

    code=$(status_code -G "$BASE/sessions" \
        --data-urlencode "sort=-id;delete from web_sessions" \
        -H "Accept: application/json" \
        -H "Authorization: Bearer ${QUERY_TOKEN}")
    expect_not_500 "Sessions hostile sort" "$code"
fi

echo ""
echo "=== Pen Test Complete ==="
echo "Pass: $PASS_COUNT  Fail: $FAIL_COUNT  Warn: $WARN_COUNT"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
    exit 1
fi
