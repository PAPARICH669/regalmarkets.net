# Regal Markets — Business Logic

All money is `decimal(18,8)` USDT and all balance changes flow through **one** place —
`WalletService` — which appends a row to the `wallet_transactions` ledger (with `balance_after`)
for every credit/debit. Engines live in `backend/app/Services/`.

## Wallets

| Wallet | Holds | Funded by | Spent on |
|--------|-------|-----------|----------|
| **A-WALLET** | capital | deposits, member transfers, E→A self transfer | package activation, member transfer, reinvest |
| **E-WALLET** | earnings | ROI, sponsor bonus, matching bonus | withdrawals, reinvest, E→A transfer |

Rule enforced in `TransferService`: **E→A allowed, A→E never**. Member-to-member moves A→A.

## ROI engine (`RoiService`, `roi:run`)

Each active package: `payout = min(daily_amount, total_return − total_paid)` → credited to
E-WALLET, logged in `roi_logs` (unique per package+date = idempotent), `total_paid` advanced.
At `total_paid ≥ total_return` (200%) the package is marked **completed** and ROI stops. Every
payout triggers the matching rollup.

## Sponsor bonus (`SponsorBonusService`)

Instant on deposit activation. Walks up the sponsor chain 5 levels paying
`[10, 5, 3, 2, 1]%` of the activated amount into each upline's E-WALLET; logged in
`sponsor_bonus_logs`.

## Matching bonus — differential rollup (`MatchingBonusService`)

The core engine. Driven by a downline's **daily ROI**, walking **up** the sponsor chain with
**unlimited depth**. Rank override %: USER 2, FAN 4, SENIOR 8, TEAM LEADER 12, GROUP LEADER 16.

```
paidPercent = 0
node = earner.sponsor
while node:
    uplinePct = matchPercent[node.rank]
    share = uplinePct - paidPercent          # differential
    if share <= 0: break                     # Rule 1: same/lower rank → STOP
    credit node E-WALLET  (roiAmount * share / 100)
    paidPercent = uplinePct
    if paidPercent >= 16: break              # top rank reached
    node = node.sponsor
```

- **Rule 1 (same rank stops):** `GROUP LEADER ← GROUP LEADER` → second GL = 16−16 = 0 → stop.
- **Rule 2 (higher rank gets the balance):** each upline earns only the override above the highest
  already paid below them.

**Worked example** — a USER earns ROI under `GROUP LEADER(16) ← TEAM LEADER(12) ← SENIOR(8) ← USER`:
SENIOR `8−0 = 8%`, TEAM LEADER `12−8 = 4%`, GROUP LEADER `16−12 = 4%`. (Asserted in
`tests/Feature/BonusEngineTest.php`.) Frozen uplines forfeit their share but the rollup continues.

## Rank engine (`RankService`, `rank:update`)

Promotes (never auto-demotes) to a fixpoint. Requirements:

| Rank | Requirement |
|------|-------------|
| USER | default |
| FAN | `total_fund ≥ 100` **and** ≥ 3 direct referrals each with ≥ 100 USDT deposit |
| SENIOR | produce ≥ 3 **FAN** legs |
| TEAM LEADER | `total_fund ≥ 500` **and** produce ≥ 3 **SENIOR** legs |
| GROUP LEADER | `total_fund ≥ 5000` **and** produce ≥ 3 **TEAM LEADER** legs |

> **Interpretation:** "produce N rank X" = **N qualifying legs** — N distinct direct downlines whose
> subtree (incl. themselves) contains a member of rank ≥ X. Each promotion writes `rank_histories`.

## Deposit → activation (`DepositService`)

On admin approval: credit A-WALLET → `total_fund +=` → activate a 200% package (locks principal out
of A-WALLET, `total_invested +=`) → pay sponsor bonuses → re-run the rank engine.

## Reinvest (`ReinvestService`) & Transfer (`TransferService`)

Reinvest: E-WALLET → A-WALLET → new package (min 10). Self transfer: E→A (min 10). Member transfer:
A→A by username/email (min 10). All validate frozen status, maintenance window and balances.

## Withdrawals (`WithdrawalService`)

From E-WALLET. Min 10, max 1,000/day (sum of today's non-rejected), configurable fee, 72-hour SLA.
Request **holds** (debits) the gross immediately; admin approve sets TXID; reject **refunds**.

## Maintenance (`MaintenanceService`)

Daily window **00:00–06:59** `Asia/Kuala_Lumpur` (live at 07:00). `MaintenanceWindow` middleware
blocks login/withdraw/transfer (admins bypass). Admin can force it on/off via `maintenance_manual`.

## Settings

`SettingsService` reads runtime values from the `settings` table, falling back to
`config/regal.php`. Admins edit ROI %, fees, limits, maintenance times via `/admin/settings`;
changes take effect immediately (cache busted on write).
