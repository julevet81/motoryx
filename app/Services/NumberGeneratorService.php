<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NumberGeneratorService
{
    /**
     * Generate a unique, sequential sale number per tenant.
     * Uses DB-level locking to prevent race conditions.
     */
    public function nextSaleNumber(int $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $last = DB::table('sales')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->max(DB::raw("CAST(SUBSTRING_INDEX(sale_number, '-', -1) AS UNSIGNED)"));

            $next = (int) $last + 1;

            return sprintf('SALE-%04d-%05d', $tenantId, $next);
        });
    }

    public function nextQuotationNumber(int $tenantId): string
    {
        return DB::transaction(function () use ($tenantId) {
            $last = DB::table('quotations')
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->max(DB::raw("CAST(SUBSTRING_INDEX(quotation_number, '-', -1) AS UNSIGNED)"));

            $next = (int) $last + 1;

            return sprintf('QUO-%04d-%05d', $tenantId, $next);
        });
    }
}
