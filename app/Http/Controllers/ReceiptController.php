<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Exception;
use Thiagoalessio\TesseractOCR\TesseractOCR;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required_without:file|string',
            'file'      => 'required_without:image_url|file|mimes:jpg,jpeg,png,pdf|max:8192',
            'amount'    => 'nullable|numeric|min:0',
            'title'     => 'nullable|string|max:255'
        ]);

        $user = $request->user();

        try {
            $imagePath = null;

            if ($request->hasFile('file')) {
                $imagePath = $request->file('file')->store('receipts', 'public');
            } else {
                $imageUrl = $request->input('image_url');
                if (preg_match('/^data:image\/(\w+);base64,/', $imageUrl, $type)) {
                    $type = strtolower($type[1]);
                    if (!in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                        return response()->json(['success' => false, 'message' => 'Invalid image type'], 422);
                    }
                    $imageBody = substr($imageUrl, strpos($imageUrl, ',') + 1);
                    $imageBody = str_replace(' ', '+', $imageBody);
                    $decoded = base64_decode($imageBody);
                    if ($decoded === false) {
                        return response()->json(['success' => false, 'message' => 'Base64 decode failed'], 422);
                    }
                    $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $type;
                    $imagePath = 'receipts/' . $fileName;
                    Storage::disk('public')->put($imagePath, $decoded);
                } else {
                    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $imagePath = null;
                    } else {
                        $imagePath = $imageUrl;
                    }
                }
            }

            $fileUrl = $imagePath ? Storage::disk('public')->url($imagePath) : $request->input('image_url');

            $receipt = Receipt::create([
                'receipt_id' => Str::uuid(),
                'user_id' => $user->id,
                'title' => $request->input('title') ?? null,
                'file_url' => $fileUrl,
                'raw_text' => null,
                'parsed_items' => null,
                'total_amount' => $request->input('amount') ?? null
            ]);

            return response()->json(['success' => true, 'message' => 'Receipt saved', 'data' => $receipt], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to save receipt', 'error' => $e->getMessage()], 500);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240'
        ]);

        $user = $request->user();

        try {
            $path = $request->file('file')->store('receipts', 'public');
            $fullPath = storage_path("app/public/{$path}");

            $ocrText = (new TesseractOCR($fullPath))->lang('eng')->run();

            $parsed = $this->parseReceipt($ocrText);

            $receipt = Receipt::create([
                'receipt_id' => Str::uuid(),
                'user_id' => $user->id,
                'file_url' => Storage::disk('public')->url($path),
                'raw_text' => $ocrText,
                'parsed_items' => $parsed['items'],
                'total_amount' => $parsed['total'] ?: null,
                'title' => $parsed['title'] ?? null
            ]);

            return response()->json(['success' => true, 'message' => 'Receipt scanned successfully.', 'data' => $receipt], 201);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'OCR/upload failed', 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $receipts = Receipt::where('user_id', $request->user()->id)->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $receipts]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $receipt = Receipt::where('id', $id)->where('user_id', $request->user()->id)->first();
        if (!$receipt) return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);
        return response()->json(['success' => true, 'data' => $receipt]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $receipt = Receipt::where('id', $id)->where('user_id', $request->user()->id)->first();
        if (!$receipt) return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);

        try {
            if ($receipt->file_url) {
                $publicUrlPrefix = Storage::disk('public')->url('');
                if (Str::startsWith($receipt->file_url, $publicUrlPrefix)) {
                    $relative = str_replace($publicUrlPrefix, '', $receipt->file_url);
                    Storage::disk('public')->delete($relative);
                }
            }
        } catch (Exception $e) {
            // ignore file deletion errors
        }

        $receipt->delete();
        return response()->json(['success' => true, 'message' => 'Receipt deleted successfully']);
    }

    private function parseReceipt(string $text): array
    {
        $lines = array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $text)));
        $items = [];
        $total = 0.0;
        $title = null;

        foreach ($lines as $line) {
            $clean = preg_replace('/[\t ]+/', ' ', $line);
            $lower = strtolower($clean);

            if (preg_match('/\b(total|grand total|amount due|balance due)\b[:\s]*([0-9,]+\.\d{1,2}|[0-9,]+)/i', $lower, $m)) {
                $num = str_replace(',', '', $m[2]);
                $total = (float)$num;
                continue;
            }

            if (preg_match('/([0-9]+(?:[.,][0-9]{1,2})?)\s*$/', $clean, $m)) {
                $amount = (float) str_replace(',', '.', str_replace(',', '', $m[1]));
                $desc = trim(substr($clean, 0, -strlen($m[0])));
                if (strlen($desc) > 0 && $amount > 0) {
                    $items[] = ['description' => $desc, 'amount' => round($amount, 2)];
                    $total += $amount;
                    if (!$title) $title = $desc;
                }
            }
        }

        return ['title' => $title ?? 'Receipt', 'items' => $items, 'total' => round($total, 2)];
    }
}
