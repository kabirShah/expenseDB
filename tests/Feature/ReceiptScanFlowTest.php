<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ReceiptScanFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_receipt_scan_creates_visible_receipt_and_expense_with_readable_details(): void
    {
        Storage::fake('public');

        $user = $this->createUser('receipt-user');
        Sanctum::actingAs($user);

        $ocrText = implode("\n", [
            'DMart Ready',
            'Milk 45.00',
            'Bread 30.00',
            'Grand Total Rs 75.00',
        ]);

        $ocr = Mockery::mock('overload:thiagoalessio\TesseractOCR\TesseractOCR');
        $ocr->shouldReceive('executable')->zeroOrMoreTimes()->andReturnSelf();
        $ocr->shouldReceive('lang')->zeroOrMoreTimes()->with('eng')->andReturnSelf();
        $ocr->shouldReceive('psm')->zeroOrMoreTimes()->with(6)->andReturnSelf();
        $ocr->shouldReceive('oem')->zeroOrMoreTimes()->with(3)->andReturnSelf();
        $ocr->shouldReceive('run')->zeroOrMoreTimes()->andReturn($ocrText);

        $uploadResponse = $this->postJson('/api/receipts/upload', [
            'image_url' => $this->tinyPngDataUri(),
        ]);

        if ($uploadResponse->status() !== 201) {
            $this->fail('Receipt upload failed: ' . $uploadResponse->getContent());
        }

        $uploadResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'DMart')
            ->assertJsonPath('data.amount', 75)
            ->assertJsonCount(3, 'data.items');

        $receiptId = $uploadResponse->json('data.receipt.id');

        $receipt = Receipt::query()->findOrFail($receiptId);
        $expense = Expense::query()->findOrFail($receipt->linked_expense_id);

        $this->assertSame('DMart', $receipt->title);
        $this->assertCount(3, $receipt->parsed_items ?? []);
        $this->assertSame('scan', $expense->source_type);
        $this->assertSame('DMart', $expense->merchant_name);
        $this->assertEquals(75.00, (float) $expense->amount);

        $this->getJson('/api/receipts?per_page=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $receipt->id)
            ->assertJsonPath('data.data.0.title', 'DMart')
            ->assertJsonPath('data.data.0.total_amount', '75.00');

        $this->getJson('/api/receipts/' . $receipt->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $receipt->id)
            ->assertJsonPath('data.title', 'DMart')
            ->assertJsonPath('data.total_amount', '75.00')
            ->assertJsonPath('data.parsed_items.0.description', 'Milk')
            ->assertJsonPath('data.parsed_items.0.amount', 45)
            ->assertJsonPath('data.parsed_items.1.description', 'Bread')
            ->assertJsonPath('data.parsed_items.1.amount', 30)
            ->assertJsonPath('data.raw_text', $ocrText);

        $this->getJson('/api/expenses?period=6months&per_page=50')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $expense->id,
                'source_type' => 'scan',
                'merchant_name' => 'DMart',
            ]);

        $this->deleteJson('/api/receipts/' . $receipt->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('receipts', [
            'id' => $receipt->id,
        ]);
    }

    private function createUser(string $prefix): User
    {
        return User::query()->create([
            'first_name' => ucfirst($prefix),
            'last_name' => 'Tester',
            'email' => $prefix . '@example.com',
            'phone' => '99999' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
            'dob' => '1995-01-01',
            'gender' => 'Other',
            'password' => Hash::make('password'),
        ]);
    }

    private function tinyPngDataUri(): string
    {
        return 'data:image/png;base64,'
            . 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WnSUs8AAAAASUVORK5CYII=';
    }
}
