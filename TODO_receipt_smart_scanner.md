# TODO - Smart Receipt Scanner (Pocket Money)

## Phase 1 — Database (completed partially)
- [x] Extend `receipts` table (add nullable smart-scanner columns).
- [x] Create `receipt_items` table.
- [x] Create `receipt_reviews` table.
- [x] Create `receipt_versions` table.
- [x] Create `receipt_activity` table.
- [x] Add SoftDeletes to `receipts` table via migration.


## Phase 2 — Models
- [x] Add `ReceiptItem` model.
- [x] Add `ReceiptReview` model.
- [x] Add `ReceiptVersion` model.
- [x] Add `ReceiptActivity` model.
- [ ] Update `Receipt` model casts/fillable (done) + ensure no accidental breaking changes.

## Phase 3 — Services + Jobs
- [ ] Implement `ReceiptOCRService` (strategy with Tesseract default).
- [ ] Implement `ReceiptParserService` (structured parsing).
- [ ] Implement `ReceiptReviewService` (status + merge OCR + user overrides).
- [ ] Implement `ReceiptService` (orchestrates pipeline).
- [ ] Implement jobs: `ProcessReceiptOCRJob`, `ParseReceiptAIJob`.

## Phase 4 — Controllers + API
- [ ] Add routes + controller endpoints for: create, parse, confirm, expense, reprocess.

## Phase 5 — Frontend
- [ ] Implement Ionic pages/components for capture, review, edit, list, details.
- [ ] Wire API integration + live recalc.


