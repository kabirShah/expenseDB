<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('financialLabel')) {
    function financialLabel(): string
    {
        return Cache::remember('financial_label', 3600, static function (): string {
            if (!Schema::hasTable('app_settings')) {
                return 'Balance';
            }

            return (string) (
                DB::table('app_settings')
                    ->where('key', 'financial_container_label')
                    ->value('value')
                ?? 'Balance'
            );
        });
    }
}

if (!function_exists('financialContainer')) {
    function financialContainer(float|int|string|null $amount, ?string $label = null): array
    {
        return [
            'amount' => round((float) ($amount ?? 0), 2),
            'label' => $label ?: financialLabel(),
        ];
    }
}
