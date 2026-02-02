#!/usr/bin/env bash
# =============================================================================
# scripts/smoke_test.sh — Smoke test A-Z pour AG-Vote
# =============================================================================
#
# Usage:
#   bash scripts/smoke_test.sh                  # test complet (serveur doit tourner)
#   BASE=http://monserveur:8080 bash scripts/smoke_test.sh
#
# Pre-requis :
#   - sudo bash database/setup_demo_az.sh (ou --reset)
#   - php -S 0.0.0.0:8080 -t public &
#
# Ce script teste le parcours A-Z complet :
#   1. Ping / DB accessible
#   2. Login 4 comptes (admin, operator, president, auditor)
#   3. Lister meetings + motions + members
#   4. Transitions : scheduled → frozen → live
#   5. Bulk attendance (6 membres)
#   6. Ouvrir motion, generer tokens, voter, fermer motion
#   7. Transitions : live → closed → validated
#
# Sortie : exit 0 si tout passe, exit 1 au premier echec.
# =============================================================================

set -uo pipefail
# Note: pas de -e car on veut continuer apres un test echoue

BASE="${BASE:-http://localhost:8080}"
API="$BASE/api/v1"
COOKIES="/tmp/agvote_smoke_$$"
STEP=0
PASS=0
FAIL=0

# Meeting + motion IDs (doivent correspondre a 08_demo_az.sql)
MEETING_ID="deadbeef-0001-4a00-8000-000000000001"
MOTION1_ID="deadbeef-0001-4a01-8000-000000000001"

# Member IDs (6 premiers)
MEMBER_IDS=(
  "aaa00001-4a00-4000-8000-000000000001"
  "aaa00001-4a00-4000-8000-000000000002"
  "aaa00001-4a00-4000-8000-000000000003"
  "aaa00001-4a00-4000-8000-000000000004"
  "aaa00001-4a00-4000-8000-000000000005"
  "aaa00001-4a00-4000-8000-000000000006"
)

# Couleurs
if [ -t 1 ]; then
  GREEN='\033[0;32m'; RED='\033[0;31m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
else
  GREEN=''; RED=''; YELLOW=''; BLUE=''; NC=''
fi

cleanup() {
  rm -f "${COOKIES}"_* 2>/dev/null || true
}
trap cleanup EXIT

# Helper : appel API avec check
# Usage: api_check "description" HTTP_METHOD URL [BODY_JSON] [COOKIE_FILE] [EXPECTED_FIELD]
api_call() {
  local desc="$1" method="$2" url="$3" body="${4:-}" cookie="${5:-}" expect="${6:-ok}"
  STEP=$((STEP + 1))

  local args=(-sS -X "$method" -H "Content-Type: application/json")
  [ -n "$cookie" ] && args+=(-b "$cookie" -c "$cookie")
  [ -n "$body" ] && args+=(-d "$body")

  local resp
  resp=$(curl "${args[@]}" "$url" 2>&1) || {
    echo -e "${RED}[FAIL]${NC} #$STEP $desc — curl error"
    FAIL=$((FAIL + 1))
    echo ""
    return 1
  }

  # Check que la reponse contient le champ attendu avec valeur true ou non-vide
  if echo "$resp" | python3 -c "
import sys, json
d = json.load(sys.stdin)
v = d.get('$expect')
sys.exit(0 if v else 1)
" 2>/dev/null; then
    echo -e "${GREEN}[PASS]${NC} #$STEP $desc"
    PASS=$((PASS + 1))
  else
    echo -e "${RED}[FAIL]${NC} #$STEP $desc"
    echo "  Response: $(echo "$resp" | head -c 300)"
    FAIL=$((FAIL + 1))
    return 1
  fi
  # Store last response for callers
  LAST_RESP="$resp"
}

# Login helper
login_as() {
  local role="$1" email="$2" pass="$3"
  local cfile="${COOKIES}_${role}"
  api_call "Login $role" POST "$API/auth_login.php" \
    "{\"email\":\"$email\",\"password\":\"$pass\"}" \
    "$cfile" "ok"
}

echo ""
echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}  AG-VOTE — Smoke Test A-Z${NC}"
echo -e "${BLUE}=========================================${NC}"
echo -e "  Serveur : $BASE"
echo ""

# ── 1. Ping ──────────────────────────────────────────────────────────────────
echo -e "${YELLOW}--- Infrastructure ---${NC}"
api_call "Ping serveur" GET "$API/ping.php" "" "" "ok"

# ── 2. Whoami (non auth → 401 attendu) ──────────────────────────────────────
STEP=$((STEP + 1))
whoami_resp=$(curl -sS "$API/whoami.php" 2>&1)
if echo "$whoami_resp" | grep -q '"ok":false'; then
  echo -e "${GREEN}[PASS]${NC} #$STEP Whoami sans auth → 401 (attendu)"
  PASS=$((PASS + 1))
else
  echo -e "${RED}[FAIL]${NC} #$STEP Whoami sans auth devrait retourner ok:false"
  FAIL=$((FAIL + 1))
fi

# ── 3. Login 4 comptes ──────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}--- Authentification ---${NC}"
login_as "admin"     "admin@ag-vote.local"     "Admin2026!"
login_as "operator"  "operator@ag-vote.local"  "Operator2026!"
login_as "president" "president@ag-vote.local" "President2026!"
login_as "auditor"   "auditor@ag-vote.local"   "Auditor2026!"

# ── 4. Whoami (auth → ok) ───────────────────────────────────────────────────
api_call "Whoami admin (auth)" GET "$API/whoami.php" "" "${COOKIES}_admin" "ok"

# ── 5. Lister meetings ──────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}--- Donnees de base ---${NC}"
api_call "Lister meetings" GET "$API/meetings.php" "" "${COOKIES}_operator" "ok"
api_call "Motions du meeting" GET "$API/motions_for_meeting.php?meeting_id=$MEETING_ID" "" "${COOKIES}_operator" "ok"

# ── 6. Transitions : scheduled → frozen → live ──────────────────────────────
echo ""
echo -e "${YELLOW}--- Cycle de vie seance ---${NC}"

# D'abord verifier le statut actuel et reset si necessaire
current_status=$(curl -sS -b "${COOKIES}_president" "$API/meetings.php" 2>/dev/null \
  | python3 -c "
import sys, json
d = json.load(sys.stdin)
data = d.get('data', {})
meetings = data.get('meetings', []) if isinstance(data, dict) else data if isinstance(data, list) else []
for m in meetings:
    if m.get('id') == '$MEETING_ID':
        print(m.get('status', 'unknown'))
        sys.exit(0)
print('unknown')
" 2>/dev/null || echo "unknown")

echo -e "  Statut actuel: $current_status"

if [ "$current_status" = "scheduled" ]; then
  api_call "Transition scheduled → frozen" POST "$API/meeting_transition.php" \
    "{\"meeting_id\":\"$MEETING_ID\",\"to_status\":\"frozen\"}" \
    "${COOKIES}_president" "ok"

  api_call "Transition frozen → live" POST "$API/meeting_transition.php" \
    "{\"meeting_id\":\"$MEETING_ID\",\"to_status\":\"live\"}" \
    "${COOKIES}_president" "ok"
elif [ "$current_status" = "frozen" ]; then
  api_call "Transition frozen → live" POST "$API/meeting_transition.php" \
    "{\"meeting_id\":\"$MEETING_ID\",\"to_status\":\"live\"}" \
    "${COOKIES}_president" "ok"
elif [ "$current_status" = "live" ]; then
  echo -e "  ${YELLOW}(deja en live, on continue)${NC}"
else
  echo -e "  ${RED}Statut inattendu: $current_status — reset la demo avec --reset${NC}"
  FAIL=$((FAIL + 1))
fi

# ── 7. Bulk attendance ──────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}--- Presences ---${NC}"

# Construire le JSON des presences
ATT_JSON="{\"meeting_id\":\"$MEETING_ID\",\"attendances\":["
for i in "${!MEMBER_IDS[@]}"; do
  [ "$i" -gt 0 ] && ATT_JSON+=","
  ATT_JSON+="{\"member_id\":\"${MEMBER_IDS[$i]}\",\"mode\":\"present\"}"
done
ATT_JSON+="]}"

api_call "Bulk attendance (6 membres)" POST "$API/attendances_bulk.php" \
  "$ATT_JSON" "${COOKIES}_operator" "ok"

# ── 8. Ouvrir motion 1 ─────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}--- Vote (motion 1) ---${NC}"

api_call "Ouvrir motion 1" POST "$API/motions_open.php" \
  "{\"motion_id\":\"$MOTION1_ID\"}" \
  "${COOKIES}_operator" "ok"

# ── 9. Generer tokens ──────────────────────────────────────────────────────
api_call "Generer vote tokens" POST "$API/vote_tokens_generate.php" \
  "{\"meeting_id\":\"$MEETING_ID\",\"motion_id\":\"$MOTION1_ID\"}" \
  "${COOKIES}_operator" "ok"

# Verifier le nombre de tokens generes
TOKEN_COUNT=$(echo "$LAST_RESP" | python3 -c "
import sys, json
d = json.load(sys.stdin)
print(d.get('data', {}).get('count', 0))
" 2>/dev/null || echo "0")
echo -e "  Tokens generes: $TOKEN_COUNT"

# ── 10. Voter (3 pour, 3 contre) via ballots_cast (motion_id + member_id + value)
VOTES=("for" "for" "for" "against" "against" "against")
VOTE_OK=0
VOTE_LAST_ERR=""
for i in 0 1 2 3 4 5; do
  STEP=$((STEP + 1))
  vote_resp=$(curl -sS -X POST -H "Content-Type: application/json" \
    -b "${COOKIES}_operator" -c "${COOKIES}_operator" \
    -d "{\"motion_id\":\"$MOTION1_ID\",\"member_id\":\"${MEMBER_IDS[$i]}\",\"value\":\"${VOTES[$i]}\"}" \
    "$API/ballots_cast.php" 2>&1)
  if echo "$vote_resp" | grep -q '"ok":true'; then
    VOTE_OK=$((VOTE_OK + 1))
  else
    VOTE_LAST_ERR="$vote_resp"
  fi
done
if [ "$VOTE_OK" -eq 6 ]; then
  echo -e "${GREEN}[PASS]${NC} #$STEP Votes cast (3 pour, 3 contre) — $VOTE_OK/6"
  PASS=$((PASS + 1))
else
  echo -e "${RED}[FAIL]${NC} #$STEP Votes cast — seulement $VOTE_OK/6"
  echo "  Dernier echec: $(echo "$VOTE_LAST_ERR" | head -c 300)"
  FAIL=$((FAIL + 1))
fi

# ── 11. Fermer motion 1 ────────────────────────────────────────────────────
api_call "Fermer motion 1" POST "$API/motions_close.php" \
  "{\"motion_id\":\"$MOTION1_ID\"}" \
  "${COOKIES}_operator" "ok"

# ── 12. Transitions : live → closed → validated ────────────────────────────
echo ""
echo -e "${YELLOW}--- Cloture et validation ---${NC}"

api_call "Transition live → closed" POST "$API/meeting_transition.php" \
  "{\"meeting_id\":\"$MEETING_ID\",\"to_status\":\"closed\"}" \
  "${COOKIES}_president" "ok"

api_call "Transition closed → validated" POST "$API/meeting_transition.php" \
  "{\"meeting_id\":\"$MEETING_ID\",\"to_status\":\"validated\"}" \
  "${COOKIES}_president" "ok"

# ── Resultat ────────────────────────────────────────────────────────────────
echo ""
echo -e "${BLUE}=========================================${NC}"
if [ "$FAIL" -eq 0 ]; then
  echo -e "${GREEN}  SMOKE TEST OK — $PASS/$((PASS + FAIL)) tests passes${NC}"
  echo -e "${BLUE}=========================================${NC}"
  echo ""
  exit 0
else
  echo -e "${RED}  SMOKE TEST ECHOUE — $PASS passes, $FAIL echecs${NC}"
  echo -e "${BLUE}=========================================${NC}"
  echo ""
  exit 1
fi
