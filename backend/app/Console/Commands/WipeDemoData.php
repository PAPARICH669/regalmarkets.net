<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-launch reset: removes ALL non-admin members and every transactional
 * record (deposits, packages, ROI, bonuses, wallets, transfers, withdrawals,
 * histories), leaving only admin accounts with zeroed wallets/stats.
 *
 * Ranks, settings and announcements are preserved. Requires --force.
 */
class WipeDemoData extends Command
{
    protected $signature = 'regal:wipe-demo {--force : Required to actually run}';

    protected $description = 'Wipe all demo/member data for a clean launch (keeps admin accounts).';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. This permanently deletes all non-admin data.');
            return self::FAILURE;
        }

        $adminIds = User::where('is_admin', true)->pluck('id');
        if ($adminIds->isEmpty()) {
            $this->error('No admin account found — aborting to avoid locking you out.');
            return self::FAILURE;
        }

        $this->warn('Admin accounts kept: ' . $adminIds->implode(', '));

        DB::transaction(function () use ($adminIds) {
            // Child/transactional tables — delete everything (pre-launch reset).
            foreach ([
                'matching_bonus_logs',
                'sponsor_bonus_logs',
                'roi_logs',
                'investment_packages',
                'withdrawals',
                'transfers',
                'deposits',
                'wallet_transactions',
                'rank_histories',
                'login_histories',
                'audit_logs',
            ] as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $n = DB::table($table)->delete();
                    $this->line("  cleared {$table}: {$n}");
                }
            }

            // Remove non-admin wallets, then non-admin users (force = hard delete).
            DB::table('wallets')->whereNotIn('user_id', $adminIds)->delete();
            $deletedUsers = User::whereNotIn('id', $adminIds)->forceDelete();
            $this->line("  deleted non-admin users: {$deletedUsers}");

            // Reset admin wallets + stats to a clean slate.
            DB::table('wallets')->whereIn('user_id', $adminIds)->update(['balance' => 0]);
            User::whereIn('id', $adminIds)->update([
                'total_invested' => 0,
                'total_fund'     => 0,
            ]);
            $this->line('  reset admin wallets & stats to 0');
        });

        $this->info('✓ Demo data wiped. System is clean and ready for real members.');
        return self::SUCCESS;
    }
}
