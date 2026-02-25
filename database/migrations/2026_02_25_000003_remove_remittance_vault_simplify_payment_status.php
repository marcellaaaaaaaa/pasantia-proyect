<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This migration is a no-op for fresh installs.
     *
     * The original remittance/vault migrations have been removed and the
     * payments/wallet_transactions table schemas were updated directly.
     * This file exists only to maintain migration order consistency.
     */
    public function up(): void
    {
        // No-op: original migrations already updated for fresh install
    }

    public function down(): void
    {
        // No-op
    }
};
