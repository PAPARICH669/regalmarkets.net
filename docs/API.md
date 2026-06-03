# Regal Markets — API Reference

Base URL: `http://localhost:8000/api` · Auth: `Authorization: Bearer <token>` (Sanctum).
All responses JSON. Validation errors → `422 { message, errors }`. Maintenance → `503`.
Frozen account → `423`. Admin-only → `403`.

## Auth & public
| Method | Path | Body | Notes |
|--------|------|------|-------|
| GET | `/maintenance-status` | — | window + countdown |
| GET | `/public-settings` | — | plans, %s, limits |
| GET | `/ranks` | — | rank table |
| POST | `/register` | username, email, password, password_confirmation, referral_code?, wallet_address? | → token + user |
| POST | `/login` | login (username/email), password | → token + user (blocked during maintenance) |
| POST | `/forgot-password` | email | |
| POST | `/reset-password` | email, password, password_confirmation | |

## Member (auth:sanctum)
| Method | Path | Body / Notes |
|--------|------|--------------|
| GET | `/me` | current user payload |
| POST | `/logout` | revoke current token |
| PUT | `/profile` | wallet_address |
| GET | `/dashboard` | wallets, totals, packages+progress, team stats, 14-day earnings series |
| GET | `/wallet-transactions?wallet=A\|E` | ledger (paginated) |
| GET / POST | `/deposits` | list / create (amount, txid?, proof? multipart) |
| GET | `/network/tree?depth=` · `/network/stats` | genealogy + downline stats |
| GET | `/logs/roi` · `/logs/sponsor` · `/logs/matching` | bonus/ROI logs (paginated) |
| GET | `/announcements` | active announcements |

**Guarded by `not.frozen` + `maintenance`:**
| Method | Path | Body |
|--------|------|------|
| GET | `/withdrawals/config` | min/max/fee/SLA |
| GET / POST | `/withdrawals` | list / request (amount, wallet_address) |
| GET | `/transfers` | history |
| POST | `/transfers/self` | amount (E→A) |
| POST | `/transfers/member` | to (username/email), amount |
| POST | `/reinvest` | amount (from E-WALLET) |

## Admin (auth:sanctum + `admin`, prefix `/admin`)
| Method | Path | Notes |
|--------|------|-------|
| GET | `/dashboard` | members, deposits, withdrawals, ROI liability, totals |
| GET | `/deposits?status=` · POST `/deposits/{id}/approve` · `/reject` | approval queue |
| GET | `/withdrawals?status=` · POST `/withdrawals/{id}/approve` (txid?) · `/reject` | |
| GET | `/members?search=&frozen=` · GET `/members/{id}` · `/members/{id}/tree` | |
| POST | `/members/{id}/freeze` · `/adjust-wallet` (type,direction,amount) · `/rank` (rank_id) | |
| GET / PUT | `/settings` | read / update business settings |
| GET / POST / PUT / DELETE | `/announcements` | CRUD |
| GET | `/maintenance` · POST `/maintenance/toggle` (manual) | |
| GET | `/logs/matching` · `/logs/sponsor` · `/logs/audit` | |
| GET | `/reports/{members\|deposits\|withdrawals}` | CSV download |

### Example
```bash
TOKEN=$(curl -s localhost:8000/api/login -H "Content-Type: application/json" \
  -d '{"login":"member","password":"password"}' | jq -r .token)
curl -s localhost:8000/api/dashboard -H "Authorization: Bearer $TOKEN" | jq
```
