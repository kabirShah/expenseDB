<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Receipt;
use App\Services\Ingestion\UnifiedExpenseIngestionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\UnifiedTransactionService;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;
use thiagoalessio\TesseractOCR\TesseractOCR;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        $receipts = Receipt::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $receipts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->upload($request);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $receipt = Receipt::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $receipt,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required_without:file|string',
            'file' => 'required_without:image_url|file|mimes:jpg,jpeg,png|max:10240',
        ]);

        $user = $request->user();

        try {
            $imagePath = null;

            if ($request->hasFile('file')) {
                $imagePath = $request->file('file')->store('receipts', 'public');
            } else {
                $imageUrl = $request->input('image_url');

                if (!preg_match('/^data:image\/(\w+);base64,/', $imageUrl, $type)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid image payload',
                        'error' => 'image_url must be a valid base64 data URI',
                    ], 422);
                }

                preg_match('/^data:image\/(\w+);base64,/', $imageUrl, $type);
                $type = strtolower($type[1] ?? 'jpg');

                $imageBody = substr($imageUrl, strpos($imageUrl, ',') + 1);
                $decoded = base64_decode($imageBody, true);

                if ($decoded === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid image payload',
                        'error' => 'image_url could not be decoded',
                    ], 422);
                }

                $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $type;
                $imagePath = 'receipts/' . $fileName;

                Storage::disk('public')->put($imagePath, $decoded);
            }

            $fullPath = storage_path("app/public/{$imagePath}");
            $this->preprocessImage($fullPath);

            $ocr = new TesseractOCR($fullPath);

            if (config('services.tesseract.executable')) {
                $ocr->executable(config('services.tesseract.executable'));
            }

            $ocrText = $ocr
                ->lang('eng')
                ->psm(6)
                ->oem(3)
                ->run();

            $parsed = $this->parseReceipt($ocrText);

            $expense = null;
            if ($parsed['total'] > 0) {
                $expense = app(UnifiedExpenseIngestionService::class)->ingest($user->id, 'scan', [
                    'merchant_name' => $parsed['title'],
                    'amount' => $parsed['total'],
                    'currency' => 'INR',
                    'payment_method' => 'Receipt Scan',
                    'transaction_type' => 'Cash',
                    'expense_date' => now(),
                    'date' => now(),
                    'description' => $parsed['title'],
                    'notes' => 'Auto from receipt OCR',
                    'receipt_url' => Storage::disk('public')->url($imagePath),
                    'status' => Expense::STATUS_ACTIVE,
                    'metadata' => [
                        'ocr_text' => $ocrText,
                        'items' => $parsed['items'],
                    ],
                ]);
                app(UnifiedTransactionService::class)->syncExpense($expense, 'scan');
            }

            $receipt = Receipt::create([
                'receipt_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'file_url' => Storage::disk('public')->url($imagePath),
                'raw_text' => $ocrText,
                'parsed_items' => $parsed['items'],
                'total_amount' => $parsed['total'],
                'title' => $parsed['title'],
                'linked_expense_id' => $expense?->id,
            ]);

            if ($expense && $expense->source_ref_id === null) {
                $expense->update(['source_ref_id' => $receipt->id]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Receipt scanned and expense added',
                'data' => [
                    'receipt' => $receipt,
                    'amount' => $parsed['total'],
                    'title' => $parsed['title'],
                    'items' => $parsed['items'],
                ],
            ], 201);
        } catch (TesseractNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR failed',
                'error' => 'Tesseract not installed/configured',
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $receipt = Receipt::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$receipt) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found',
            ], 404);
        }

        $publicUrl = $receipt->file_url;
        $path = str_contains($publicUrl, '/storage/')
            ? ltrim(str_replace('/storage/', '', parse_url($publicUrl, PHP_URL_PATH) ?: ''), '/')
            : null;

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $receipt->delete();

        return response()->json([
            'success' => true,
            'message' => 'Receipt deleted',
        ]);
    }

    private function parseReceipt(string $text): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);

        $items = [];
        $total = 0;
        $title = 'Receipt';

        if (stripos($text, 'dmart') !== false) {
            $title = 'DMart';
        }

        $keywordTotals = [];
        $allValues = [];

        foreach ($lines as $line) {
            $clean = trim(preg_replace('/\s+/', ' ', $line));

            if ($clean === '') {
                continue;
            }

            if (preg_match('/(grand total|total|amount)[^\d]*rs\.?\s*([0-9]+(\.[0-9]{1,2})?)/i', $clean, $m)) {
                $keywordTotals[] = (float) $m[2];
            }

            if (preg_match_all('/\d+\.\d{2}/', $clean, $matches)) {
                foreach ($matches[0] as $num) {
                    $allValues[] = (float) $num;
                }
            }

            if (preg_match('/(.+?)\s+(\d+\.\d{2})$/', $clean, $m)) {
                $items[] = [
                    'description' => $m[1],
                    'amount' => (float) $m[2],
                ];
            }
        }

        if ($keywordTotals !== []) {
            $total = max($keywordTotals);
        } elseif ($allValues !== []) {
            $total = $this->pickLikelyTotal($allValues);
        }

        return [
            'title' => $title,
            'items' => $items,
            'total' => round($total, 2),
        ];
    }

    private function preprocessImage(string $fullPath): void
    {
        if (!is_file($fullPath) || !function_exists('getimagesize')) {
            return;
        }

        $imageInfo = @getimagesize($fullPath);
        if (!$imageInfo || !isset($imageInfo[2])) {
            return;
        }

        $imageType = $imageInfo[2];
        $image = match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($fullPath) : null,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($fullPath) : null,
            default => null,
        };

        if (!$image) {
            return;
        }

        $image = $this->applyExifOrientation($image, $fullPath, $imageType);

        if (function_exists('imagefilter')) {
            @imagefilter($image, IMG_FILTER_GRAYSCALE);
            @imagefilter($image, IMG_FILTER_CONTRAST, -15);
            @imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
        }

        if (function_exists('imageconvolution')) {
            @imageconvolution($image, [
                [-1, -1, -1],
                [-1, 16, -1],
                [-1, -1, -1],
            ], 8, 0);
        }

        match ($imageType) {
            IMAGETYPE_JPEG => @imagejpeg($image, $fullPath, 90),
            IMAGETYPE_PNG => @imagepng($image, $fullPath, 6),
            default => null,
        };

        imagedestroy($image);
    }

    private function applyExifOrientation(mixed $image, string $fullPath, int $imageType): mixed
    {
        if ($imageType !== IMAGETYPE_JPEG || !function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $image;
        }

        $exif = @exif_read_data($fullPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => @imagerotate($image, 180, 0),
            6 => @imagerotate($image, -90, 0),
            8 => @imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function pickLikelyTotal(array $values): float
    {
        $filtered = array_values(array_filter($values, static fn (float $value) => $value > 0));
        sort($filtered);

        if ($filtered === []) {
            return 0;
        }

        $candidates = array_slice($filtered, -5);

        foreach ($candidates as $candidate) {
            $closeValues = array_filter(
                $filtered,
                static fn (float $value) => $value <= $candidate && ($candidate - $value) <= 5
            );

            if (count($closeValues) >= 2) {
                continue;
            }

            return $candidate;
        }

        return end($candidates) ?: 0;
    }
}
