#!/usr/bin/env bash
# Adversarial curl probes for auth endpoints — run against local Sail (http://localhost/api).
#
# Covers: enumeration, injection, rate limits, token abuse, remember-me + CSRF,
# suspensions, soft-delete, new read endpoints (audit-logs, permissions, clients),
# OAuth client-credentials, and queued audit persistence.
#
# Usage:
#   ./vendor/bin/sail artisan migrate:fresh --seed
#   bash scripts/pen-test-auth.sh
#
# Optional env:
#   PEN_TEST_BASE=http://localhost/api
#   PEN_TEST_HOST=http://localhost
#   PEN_TEST_SAIL=./vendor/bin/sail   # empty to skip tinker-backed checks
set -uo pipefail
set -o errtrace

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
    echo "FAIL  $1"
}

warn() {
    WARN_COUNT=$((WARN_COUNT + 1))
    echo "WARN  $1 — $2"
}

info() {
    echo "INFO  $1"
}

artisan_tinker() {
    if [[ -z "$SAIL" || ! -x "$SAIL" ]]; then
        echo "skip"
        return 1
    fi

    "$SAIL" artisan tinker --execute="$1" 2>/dev/null | tail -1
}

status_code() {
    curl -s -o "$BODY_FILE" -w '%{http_code}' "$@"
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
        fail "$label (expected $expected, got $actual)"
    fi
}

expect_not_500() {
    local label="$1"
    local code="$2"

    if [[ "$code" == "500" ]]; then
        fail "$label caused 500"
    else
        pass "$label returned $code (not 500)"
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
        curl -s -X POST "$BASE/login" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "{\"email\":\"${email}\",\"password\":\"${password}\",${extra_json}}"
    else
        curl -s -X POST "$BASE/login" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "{\"email\":\"${email}\",\"password\":\"${password}\"}"
    fi | json_token
}

issue_token() {
    local email="$1"
    artisan_tinker "echo App\\Models\\User::where('email','${email}')->first()?->createToken('pen-test')->plainTextToken ?? '';"
}

# Drain queued auth-audit listeners (RecordAuthAuditLog is ShouldQueue).
drain_audit_queue() {
    if [[ -z "$SAIL" || ! -x "$SAIL" ]]; then
        return 0
    fi

    "$SAIL" artisan queue:work --stop-when-empty --max-time=20 -n -q 2>/dev/null || true
}

suspend_user() {
    local email="$1"
    artisan_tinker "App\\Models\\User::where('email','${email}')->first()?->forceFill(['suspended_at'=>now()])->save(); echo 'suspended';"
}

oauth_token() {
    curl -s -X POST "$BASE/oauth/token" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$1" | json_token
}

echo "=== Auth Pen Test (adversarial) ==="
echo "Base: $BASE"
echo "Host: $HOST"
echo ""

# --- 1. Account enumeration ---
echo "--- 1. Account enumeration ---"
post_json "$BASE/login" -d "{\"email\":\"missing@example.com\",\"password\":\"${STRONG_PASS}\"}"
UNKNOWN_MSG="$(json_errors_email)"

post_json "$BASE/login" -d '{"email":"admin@example.com","password":"WrongPass1"}'
WRONG_MSG="$(json_errors_email)"

if [[ "$UNKNOWN_MSG" == "$WRONG_MSG" && "$UNKNOWN_MSG" == "Invalid Credentials" ]]; then
    pass "Login unknown vs wrong password identical generic message"
else
    fail "Login enumeration leak unknown='$UNKNOWN_MSG' wrong='$WRONG_MSG'"
fi

post_json "$BASE/register" -d "{\"name\":\"Probe\",\"email\":\"admin@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
REGISTER_MSG="$(json_errors_email)"
if [[ "$REGISTER_MSG" == "Invalid Credentials" ]]; then
    pass "Register duplicate email returns generic message"
else
    fail "Register enumeration leak: '$REGISTER_MSG'"
fi

# --- 2. Injection-shaped input (no 500) ---
echo "--- 2. Injection-shaped input ---"
for payload in "' OR 1=1--" "'; DROP TABLE users;--" "admin@example.com'--" "%' OR '1'='1" "1;SELECT * FROM users"; do
    code=$(post_json_status "$BASE/login" -d "{\"email\":\"${payload}\",\"password\":\"x\"}")
    expect_not_500 "Login SQLi-shaped email" "$code"
done

code=$(post_json_status "$BASE/register" -d "{\"name\":\"' OR 1=1--\",\"email\":\"sqli-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
expect_not_500 "Register SQLi-shaped name" "$code"

code=$(post_json_status "$BASE/login" -d '{"email":"admin@example.com","password":"'"'"' OR 1=1--"}')
expect_not_500 "Login SQLi-shaped password" "$code"

# --- 3. Malformed transport ---
echo "--- 3. Malformed transport ---"
code=$(status_code -X POST "$BASE/login" -H "Content-Type: application/json" -d '{')
expect_code "Malformed JSON body" "422" "$code"

code=$(status_code -X POST "$BASE/login" -H "Content-Type: application/json" -d '')
expect_code "Empty JSON body" "422" "$code"

code=$(status_code -X POST "$BASE/login" -H "Content-Type: text/plain" -d 'email=admin@example.com&password=x')
if [[ "$code" == "422" || "$code" == "415" ]]; then
    pass "Wrong content-type rejected ($code)"
else
    warn "Wrong content-type" "HTTP $code"
fi

# --- 4. Rate limiting ---
echo "--- 4. Rate limiting ---"
LIMITED=0
for _ in $(seq 1 15); do
    code=$(post_json_status "$BASE/login" -d '{"email":"brute@example.com","password":"wrong"}')
    if [[ "$code" == "429" ]]; then LIMITED=1; break; fi
done
if [[ "$LIMITED" == "1" ]]; then
    pass "Login rate limit triggered (429)"
else
    warn "Login rate limit" "No 429 after 15 attempts"
fi

REG_LIMITED=0
REG_BRUTE_EMAIL="rate-brute-${RANDOM}@example.com"
for _ in $(seq 1 15); do
    post_json "$BASE/register" -d "{\"name\":\"Rate\",\"email\":\"${REG_BRUTE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
    code="$(json_status)"
    if [[ "$code" == "429" ]]; then REG_LIMITED=1; break; fi
done
if [[ "$REG_LIMITED" == "1" ]]; then
    pass "Register rate limit triggered (429)"
else
    warn "Register rate limit" "No 429 after 15 attempts"
fi

# --- 5. Mass assignment on register ---
echo "--- 5. Mass assignment on register ---"
MASS_EMAIL="hacker-${RANDOM}@example.com"
post_json "$BASE/register" -d "{\"name\":\"Hacker\",\"email\":\"${MASS_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\",\"team_id\":1,\"is_admin\":true,\"email_verified_at\":\"2026-01-01T00:00:00Z\",\"role\":\"Admin\"}"
code="$(json_status)"
if [[ "$code" == "201" ]]; then
    RESULT="$(artisan_tinker "\$u=App\\Models\\User::where('email','${MASS_EMAIL}')->first(); \$ok=\$u && \$u->team_id===null && \$u->email_verified_at===null && \$u->roles->pluck('name')->first()==='User'; echo \$ok ? 'ok' : 'fail';")"
    if [[ "$RESULT" == "ok" ]]; then
        pass "Mass assignment ignored (null team, User role)"
    else
        fail "Mass assignment may have elevated privileges"
    fi
else
    pass "Register with extra fields rejected or blocked ($code)"
fi

# --- 6. Password policy ---
echo "--- 6. Password policy ---"
code=$(post_json_status "$BASE/register" -d "{\"name\":\"Weak\",\"email\":\"weak-${RANDOM}@example.com\",\"password\":\"short\",\"password_confirmation\":\"short\"}")
expect_code "Weak password rejected" "422" "$code"

code=$(post_json_status "$BASE/register" -d "{\"name\":\"Mismatch\",\"email\":\"mismatch-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"DifferentPass12\"}")
expect_code "Password confirmation mismatch" "422" "$code"

code=$(post_json_status "$BASE/register" -d "{\"name\":\"NoDigits\",\"email\":\"nodigits-${RANDOM}@example.com\",\"password\":\"SecretPassword\",\"password_confirmation\":\"SecretPassword\"}")
expect_code "Password without numbers rejected" "422" "$code"

code=$(post_json_status "$BASE/register" -d "{\"name\":\"Breached\",\"email\":\"breached-${RANDOM}@example.com\",\"password\":\"Password123\",\"password_confirmation\":\"Password123\"}")
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
    fail "Could not obtain admin token for revocation tests"
else
    curl -s -o "$BODY_FILE" -X POST "$BASE/logout" -H "Accept: application/json" -H "Authorization: Bearer ${REV_TOKEN}"
    code=$(auth_get "$BASE/users" "$REV_TOKEN")
    expect_code "Token invalid after logout" "401" "$code"
fi

# --- 10. Remember-me boundaries ---
echo "--- 10. Remember-me boundaries ---"
code=$(post_json_status "$BASE/login/remember")
expect_code "Remember without session" "401" "$code"

# Fresh cookie jar, then a stateful login with remember:true.
rm -f "$COOKIE_JAR"
COOKIE_JAR="$(mktemp)"
begin_stateful_session
XSRF="$(read_xsrf_token)"
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}" \
    -d '{"email":"manager@example.com","password":"password","remember":true}' -o "$BODY_FILE"

# Restore without the CSRF header must be blocked.
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/")
if [[ "$code" == "419" || "$code" == "401" ]]; then
    pass "Remember without CSRF header blocked ($code)"
else
    fail "Remember without CSRF allowed ($code)"
fi

# Restore with a fresh CSRF header should succeed.
begin_stateful_session
XSRF="$(read_xsrf_token)"
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}")
if [[ "$code" == "200" ]]; then
    pass "Remember restore with cookie and CSRF (200)"
else
    warn "Remember cookie flow" "HTTP $code"
fi

# --- 11. Soft-deleted account ---
echo "--- 11. Soft-deleted account ---"
DELETE_EMAIL="deleted-${RANDOM}@example.com"
post_json "$BASE/register" -d "{\"name\":\"Delete Me\",\"email\":\"${DELETE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
artisan_tinker "App\\Models\\User::where('email','${DELETE_EMAIL}')->first()?->delete(); echo 'deleted';"
post_json "$BASE/login" -d "{\"email\":\"${DELETE_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
MSG="$(json_errors_email)"
if [[ "$MSG" == "Invalid Credentials" ]]; then
    pass "Soft-deleted user login generic error"
else
    fail "Soft-delete leak: '$MSG'"
fi

# --- 12. Authorization boundaries ---
echo "--- 12. Authorization boundaries ---"
reset_rate_limits
MANAGER_TOKEN="$(login_token manager@example.com password)"
ADMIN_TOKEN="$(login_token admin@example.com password)"
USER_EMAIL="userrole-${RANDOM}@example.com"
post_json "$BASE/register" -d "{\"name\":\"User Role\",\"email\":\"${USER_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
USER_TOKEN="$(json_path 'data.plain_text_token')"

if [[ -z "$MANAGER_TOKEN" || -z "$USER_TOKEN" ]]; then
    fail "Could not obtain tokens for authorization boundary tests"
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
    warn "Token IDOR" "No user token available"
else
    code=$(auth_delete "$BASE/tokens/1" "$USER_TOKEN")
    if [[ "$code" == "403" || "$code" == "404" ]]; then
        pass "Cannot delete arbitrary token id ($code)"
    else
        fail "Token IDOR delete returned $code"
    fi
fi

# --- 14. Token abilities cannot bypass role policy ---
echo "--- 14. Token ability escalation ---"
if [[ -z "${USER_TOKEN:-}" ]]; then
    warn "Token ability escalation" "No user token available"
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
post_json "$BASE/register" -d "{\"name\":\"<script>alert(1)</script>Bob\",\"email\":\"xss-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
NAME="$(json_path 'data.user.name')"
if [[ "$NAME" != *"<script>"* ]]; then
    pass "Register name strips script tags ($NAME)"
else
    fail "XSS in register name persisted"
fi

post_json "$BASE/login" -d '{"email":"admin@example.com","password":"password","device_name":"<img src=x onerror=alert(1)>CLI"}'
TOKEN_NAME="$(json_path 'data.token.name')"
if [[ "$TOKEN_NAME" != *"<img"* ]]; then
    pass "Login device_name strips markup ($TOKEN_NAME)"
else
    fail "XSS in login device_name persisted"
fi

# --- 16. Oversized input ---
echo "--- 16. Oversized input ---"
LONG="$(python3 -c "print('a'*10000)")"
code=$(post_json_status "$BASE/login" -d "{\"email\":\"${LONG}@example.com\",\"password\":\"x\"}")
if [[ "$code" == "422" || "$code" == "413" ]]; then
    pass "Oversized email rejected ($code)"
else
    warn "Oversized email" "HTTP $code"
fi

code=$(post_json_status "$BASE/register" -d "{\"name\":\"${LONG}\",\"email\":\"longname-${RANDOM}@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
if [[ "$code" == "422" || "$code" == "413" ]]; then
    pass "Oversized name rejected ($code)"
else
    warn "Oversized name" "HTTP $code"
fi

# --- 17. HTTP verb tampering ---
echo "--- 17. HTTP verb tampering ---"
code=$(status_code -X GET "$BASE/login")
expect_code "GET /login" "405" "$code"

code=$(status_code -X PUT "$BASE/register" -H "Content-Type: application/json" -d "{\"name\":\"X\",\"email\":\"x@example.com\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}")
expect_code "PUT /register" "405" "$code"

if [[ -n "${ADMIN_TOKEN:-}" ]]; then
    code=$(auth_delete "$BASE/logout" "$ADMIN_TOKEN")
    if [[ "$code" == "405" || "$code" == "401" ]]; then
        pass "DELETE /logout blocked ($code)"
    else
        fail "DELETE /logout returned $code"
    fi
else
    warn "DELETE /logout" "No admin token available"
fi

# --- 18. Audit trail ---
echo "--- 18. Audit trail ---"
drain_audit_queue
COUNT="$(artisan_tinker "echo App\\Models\\AuthAuditLog::where('event','Login Failed')->count();")"
if [[ "${COUNT:-0}" =~ ^[0-9]+$ && "${COUNT:-0}" -gt 0 ]]; then
    pass "Failed logins recorded in audit ($COUNT rows)"
else
    fail "No failed login audit rows after queue drain (got '$COUNT')"
fi

# --- 19. Email normalisation ---
echo "--- 19. Email normalisation ---"
reset_rate_limits
CASE_EMAIL="CASE-${RANDOM}@EXAMPLE.COM"
post_json "$BASE/register" -d "{\"name\":\"Case\",\"email\":\"${CASE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
EMAIL="$(json_path 'data.user.email')"
LOWER="$(echo "$CASE_EMAIL" | tr '[:upper:]' '[:lower:]')"
if [[ "$EMAIL" == "$LOWER" ]]; then
    pass "Register lowercases email ($EMAIL)"
else
    fail "Email case not normalised: $EMAIL"
fi

post_json "$BASE/register" -d "{\"name\":\"Dup\",\"email\":\"${LOWER}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
expect_code "Duplicate email after normalisation" "422" "$(json_status)"

post_json "$BASE/login" -d '{"email":"ADMIN@EXAMPLE.COM","password":"password"}'
if [[ "$(json_status)" == "200" ]]; then
    pass "Login accepts case-variant email for known account"
else
    fail "Login case-variant email failed ($(json_status))"
fi

# --- 20. Logout kills all sessions ---
echo "--- 20. Logout kills all sessions ---"
reset_rate_limits
T1="$(login_token admin@example.com password)"
T2="$(login_token admin@example.com password)"
if [[ -z "$T1" || -z "$T2" ]]; then
    fail "Could not obtain two admin tokens for logout-all test"
else
    curl -s -o "$BODY_FILE" -X POST "$BASE/logout" -H "Accept: application/json" -H "Authorization: Bearer ${T1}"
    c1=$(auth_get "$BASE/users" "$T1")
    c2=$(auth_get "$BASE/users" "$T2")
    if [[ "$c1" == "401" && "$c2" == "401" ]]; then
        pass "Logout revokes ALL tokens"
    else
        fail "Token leak after logout t1=$c1 t2=$c2"
    fi
fi

# --- 21. Response leakage ---
echo "--- 21. Response leakage ---"
LOGIN_JSON="$(curl -s -X POST "$BASE/login" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"password"}')"
HAS_PW="$(echo "$LOGIN_JSON" | python3 -c "import json,sys; d=json.load(sys.stdin); print('password' in str(d).lower())" 2>/dev/null || echo "False")"
if [[ "$HAS_PW" == "False" ]]; then
    pass "No password field in login response"
else
    fail "Password material in login response"
fi

# --- 22. Admin force-logout ---
echo "--- 22. Admin force-logout ---"
reset_rate_limits
ADMIN_TOKEN="$(issue_token admin@example.com)"
MANAGER_TOKEN="$(issue_token manager@example.com)"
FORCE_EMAIL="force-target-${RANDOM}@example.com"
post_json "$BASE/register" -d "{\"name\":\"Force Target\",\"email\":\"${FORCE_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
FORCE_TOKEN="$(json_path 'data.plain_text_token')"
TARGET_ID="$(artisan_tinker "echo App\\Models\\User::where('email','${FORCE_EMAIL}')->value('id');")"

if [[ -z "$ADMIN_TOKEN" || -z "$MANAGER_TOKEN" || -z "$FORCE_TOKEN" ]]; then
    fail "Could not issue tokens for force-logout tests"
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
        warn "Admin force-logout" "Could not resolve target user id"
    fi

    code=$(auth_post "$BASE/users/logout" "$ADMIN_TOKEN" -d '{"ids":[999999]}')
    expect_code "Force-logout unknown id" "422" "$code"
fi

# --- 23. Timing side-channel (rough) ---
echo "--- 23. Timing side-channel (rough) ---"
T_UNKNOWN="$(curl -s -o /dev/null -w '%{time_total}' -X POST "$BASE/login" -H "Content-Type: application/json" -d "{\"email\":\"nonexistent999@example.com\",\"password\":\"${STRONG_PASS}\"}")"
T_WRONG="$(curl -s -o /dev/null -w '%{time_total}' -X POST "$BASE/login" -H "Content-Type: application/json" -d '{"email":"admin@example.com","password":"WrongPass1"}')"
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
post_json "$BASE/register" -d "{\"name\":\"Suspended User\",\"email\":\"${SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"
SUSPEND_TOKEN="$(json_path 'data.plain_text_token')"
suspend_user "$SUSPEND_EMAIL"

post_json "$BASE/login" -d "{\"email\":\"${SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
MSG="$(json_errors_email)"
if [[ "$MSG" == "Invalid Credentials" ]]; then
    pass "Suspended user login returns generic Invalid Credentials"
else
    fail "Suspended login leak or success: status=$(json_status) msg='$MSG'"
fi

if [[ -n "$SUSPEND_TOKEN" ]]; then
    code=$(auth_get "$BASE/me" "$SUSPEND_TOKEN")
    expect_code "Suspended bearer token rejected" "403" "$code"

    curl -s -o "$BODY_FILE" -X GET "$BASE/me" \
        -H "Accept: application/json" -H "Authorization: Bearer ${SUSPEND_TOKEN}"
    if [[ "$(json_message)" == "Account Suspended" ]]; then
        pass "Suspended response message is Account Suspended"
    else
        warn "Suspended message" "'$(json_message)'"
    fi
else
    warn "Suspended token probe" "No token before suspension"
fi

# --- 25. New read endpoints ---
echo "--- 25. New read endpoints ---"
reset_rate_limits
ADMIN_TOKEN="$(login_token admin@example.com password)"
MANAGER_TOKEN="$(login_token manager@example.com password)"
USER_TOKEN="$(login_token test@example.com password)"

if [[ -z "$ADMIN_TOKEN" || -z "$MANAGER_TOKEN" || -z "$USER_TOKEN" ]]; then
    fail "Could not obtain tokens for new endpoint probes"
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
        warn "Audit log show IDOR" "No audit row id"
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
        warn "Service token probes" "Could not exchange demo client credentials"
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
if [[ "$OAUTH_MSG" == "Invalid Credentials" ]]; then
    pass "OAuth wrong secret returns generic Invalid Credentials"
else
    fail "OAuth enumeration leak: '$OAUTH_MSG'"
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
if [[ "$UNKNOWN_OAUTH" == "Invalid Credentials" && "$UNKNOWN_OAUTH" == "$OAUTH_MSG" ]]; then
    pass "OAuth unknown client matches wrong secret message"
else
    fail "OAuth client_id enumeration unknown='$UNKNOWN_OAUTH' wrong='$OAUTH_MSG'"
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
        fail "Suspended service user OAuth returned $(json_status)"
    fi
else
    warn "Suspended OAuth client" "Could not create suspended client"
fi

OAUTH_LIMITED=0
for _ in $(seq 1 15); do
    code=$(post_json_status "$BASE/oauth/token" -d '{"grant_type":"client_credentials","client_id":"demo-integration-client","client_secret":"wrong"}')
    if [[ "$code" == "429" ]]; then OAUTH_LIMITED=1; break; fi
done
if [[ "$OAUTH_LIMITED" == "1" ]]; then
    pass "OAuth token rate limit triggered (429)"
else
    warn "OAuth rate limit" "No 429 after 15 bad attempts"
fi

# --- 27. Remember-me + suspension ---
echo "--- 27. Remember-me + suspension ---"
reset_rate_limits
REMEMBER_SUSPEND_EMAIL="remember-suspend-${RANDOM}@example.com"
post_json "$BASE/register" -d "{\"name\":\"Remember Suspend\",\"email\":\"${REMEMBER_SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"password_confirmation\":\"${STRONG_PASS}\"}"

rm -f "$COOKIE_JAR"
COOKIE_JAR="$(mktemp)"
begin_stateful_session
XSRF="$(read_xsrf_token)"
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST "$BASE/login" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}" \
    -d "{\"email\":\"${REMEMBER_SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\",\"remember\":true}" -o "$BODY_FILE"

suspend_user "$REMEMBER_SUSPEND_EMAIL"
begin_stateful_session
XSRF="$(read_xsrf_token)"
code=$(status_code -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST "$BASE/login/remember" \
    -H "Accept: application/json" \
    -H "Origin: http://localhost" \
    -H "Referer: http://localhost/" \
    -H "X-XSRF-TOKEN: ${XSRF}")
if [[ "$code" == "401" ]]; then
    pass "Remember-me blocked for suspended user (401)"
else
    fail "Remember-me allowed for suspended user ($code)"
fi

post_json "$BASE/login" -d "{\"email\":\"${REMEMBER_SUSPEND_EMAIL}\",\"password\":\"${STRONG_PASS}\"}"
if [[ "$(json_errors_email)" == "Invalid Credentials" ]]; then
    pass "Suspended user cannot password-login after remember cookie"
else
    fail "Suspended user password login after remember: '$(json_errors_email)'"
fi

# --- 28. Permission catalog query hardening ---
echo "--- 28. Permission catalog query hardening ---"
if [[ -n "${ADMIN_TOKEN:-}" ]]; then
    code=$(auth_get "$BASE/permissions?include=user" "$ADMIN_TOKEN")
    if [[ "$code" == "422" ]]; then
        pass "Permissions index rejects include=user (422)"
    else
        fail "Permissions index allowed hostile include ($code)"
    fi

    code=$(auth_get "$BASE/permissions?sort=created_at" "$ADMIN_TOKEN")
    if [[ "$code" == "422" ]]; then
        pass "Permissions index rejects unsupported sort (422)"
    else
        fail "Permissions index allowed unsupported sort ($code)"
    fi
else
    warn "Permission catalog hardening" "No admin token"
fi

echo ""
echo "=== Pen test complete ==="
echo "PASS: $PASS_COUNT  FAIL: $FAIL_COUNT  WARN: $WARN_COUNT"

if [[ "$FAIL_COUNT" -gt 0 ]]; then
    exit 1
fi
