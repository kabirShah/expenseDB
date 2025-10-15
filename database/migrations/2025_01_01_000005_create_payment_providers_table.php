<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('provider_id')->unique();
            $table->string('name')->comment('Google Pay, PhonePe, HDFC, Kotak, ICICI, etc.');
            $table->string('type')->comment('upi, bank, wallet, card_network');
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable()->comment('Provider-specific configuration (API keys, endpoints, etc.)');
            $table->json('supported_features')->nullable()->comment('Array of supported features');
            $table->decimal('transaction_fee_percentage', 5, 2)->default(0);
            $table->decimal('min_transaction_amount', 10, 2)->default(0);
            $table->decimal('max_transaction_amount', 10, 2)->nullable();
            $table->timestamps();
        });

        // Insert default payment providers
        DB::table('payment_providers')->insert([
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'Google Pay',
                'type' => 'upi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'PhonePe',
                'type' => 'upi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'HDFC Bank',
                'type' => 'bank',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'Kotak Mahindra Bank',
                'type' => 'bank',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'ICICI Bank',
                'type' => 'bank',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'provider_id' => (string) Str::uuid(),
                'name' => 'Paytm',
                'type' => 'wallet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_providers');
    }
};
