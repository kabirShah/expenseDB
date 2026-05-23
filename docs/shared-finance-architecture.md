# Shared Finance Architecture

## Goal

Pocket Money extends the existing personal expense tracker into a Splitwise-style shared finance layer without replacing the current expense, wallet, transaction, SMS parsing, analytics, recurring expense, or OTP-authenticated API modules.

## Core Principle

Shared expenses reuse the existing `expenses` table. Shared ownership and settlement state lives in:

- `expense_groups`
- `group_members`
- `expense_splits`
- `settlements`
- `friends`
- `device_contacts`
- `recurring_shared_expenses`
- `expense_comments`
- `activity_logs`

The older `/groups`, `/expenses`, `/settlements`, and `/splitwise` APIs remain available. New clients should prefer the `/shared/*` facade for contact sync, split calculation, comments, recurring shared expenses, activity, and analytics.

## Backend Folder Structure

- `app/Models/Friend.php`
- `app/Models/DeviceContact.php`
- `app/Models/RecurringSharedExpense.php`
- `app/Models/ExpenseComment.php`
- `app/Models/ActivityLog.php`
- `app/Services/ContactSyncService.php`
- `app/Services/SharedSplitCalculationService.php`
- `app/Services/GroupExpenseService.php`
- `app/Http/Controllers/SharedContactController.php`
- `app/Http/Controllers/SharedSplitController.php`
- `app/Http/Controllers/ExpenseCommentController.php`
- `app/Http/Controllers/RecurringSharedExpenseController.php`
- `app/Http/Controllers/SharedActivityController.php`
- `app/Http/Controllers/SharedAnalyticsController.php`

## API Contracts

All APIs require Sanctum auth.

### Friends And Contacts

`GET /api/shared/friends`

Returns paginated friends ordered by favorites, recent use, and frequency.

`POST /api/shared/contacts/sync`

```json
{
  "contacts": [
    {
      "device_contact_id": "native-id",
      "name": "Jay Shah",
      "phone": "+91 9999999999",
      "email": "jay@example.com"
    }
  ]
}
```

The backend normalizes phone numbers, matches registered users by phone/email, and stores only the minimum contact data needed for matching/invites.

`POST /api/shared/contacts/{contact}/invite`

Creates a friend request for registered users or marks an invite for non-users.

### Split Calculator

`POST /api/shared/splits/calculate`

Supports `equal`, `exact`, `custom`, `percentage`, `shares`, `share`, `item`, `itemized`, and `item_based`.

```json
{
  "amount": 1000,
  "split_type": "percentage",
  "participants": [
    { "user_id": 1, "percentage": 40 },
    { "user_id": 2, "percentage": 30 },
    { "user_id": 3, "percentage": 30 }
  ]
}
```

### Group Expense

`POST /api/groups/{group}/expenses`

Creates an expense in `expenses`, then creates rows in `expense_splits`. Supports multiple payers, itemized assignments, duplicate prevention, and transaction linking.

```json
{
  "title": "Dinner",
  "amount": 2400,
  "expense_date": "2026-05-14",
  "split_type": "itemized",
  "merchant_name": "Swiggy",
  "linked_transaction_id": 25,
  "payers": [
    { "user_id": 1, "amount_paid": 2400 }
  ],
  "participants": [
    { "user_id": 1 },
    { "user_id": 2 },
    { "user_id": 3 }
  ],
  "items": [
    { "name": "Pizza", "amount": 1000, "user_ids": [1, 2] },
    { "name": "Dessert", "amount": 500, "user_ids": [3] },
    { "name": "Drinks", "amount": 900, "user_ids": [1, 2, 3] }
  ]
}
```

### Balances

`GET /api/groups/{group}/balances`

Uses `GroupExpenseService::balancesForGroup()` to return net balances and simplified transfers.

`POST /api/shared/balances/simplify`

Accepts a raw balance map and returns minimized settlement transfers.

### Comments And Reactions

`GET /api/shared/expenses/{expense}/comments`

`POST /api/shared/expenses/{expense}/comments`

```json
{
  "comment": "I paid this from UPI.",
  "reaction": "thumbs_up"
}
```

### Recurring Shared Expenses

`GET /api/shared/recurring-expenses`

`POST /api/shared/recurring-expenses`

Stores templates for rent, subscriptions, utilities, salaries, and EMI splits. A scheduled job can later materialize these through `GroupExpenseService`.

### Activity And Analytics

`GET /api/shared/activity`

`GET /api/shared/analytics/summary`

Returns group count, shared totals, owed/receivable/net values, monthly trend, top groups, and category totals.

## Mobile Structure

- `src/app/models/shared-finance.model.ts`
- `src/app/services/shared-finance.service.ts`
- `src/app/services/shared-offline-queue.service.ts`

Recommended lazy modules:

- `pages/shared/friends`
- `pages/shared/contact-sync`
- `pages/shared/groups`
- `pages/shared/group-detail`
- `pages/shared/expense-editor`
- `pages/shared/split-calculator`
- `pages/shared/settlements`
- `pages/shared/activity`
- `pages/shared/analytics`
- `pages/shared/search`

## Offline Sync

`SharedOfflineQueueService` stores operations in Ionic Storage:

- `create_group_expense`
- `create_settlement`
- `comment`
- `sync_contacts`

Flush runs when the app comes online. Conflict handling should use server-side duplicate keys made from amount, merchant/title, date, payment method, transaction reference, and linked transaction.

## AI And Automation Hooks

Shared expense suggestions should read:

- Recent groups from `expense_groups`
- Frequent contacts from `friends.usage_count`
- Merchant patterns from `expenses.merchant_name`
- Existing SMS/notification parsed transactions
- Duplicate fingerprints from `expenses.duplicate_key`

Suggested background jobs:

- `SuggestSharedExpenseJob`
- `ProcessReceiptOcrJob`
- `GenerateRecurringSharedExpenseJob`
- `SendSharedFinanceNotificationJob`
- `SyncExchangeRatesJob`
- `RecalculateGroupBalancesJob`

## Payments And Pro Features

Settlements already store `method`, `reference_id`, and `metadata`, so UPI, QR, Razorpay, Stripe, PayPal, wallet settlement, and bank transfer integrations can attach payment provider references without schema rewrites.

Pro feature gates should live in a subscription/entitlement service and check:

- OCR receipt scanning
- AI recommendations
- Multi-currency conversion
- Advanced reports
- Unlimited exports
- Smart recurring automation

## Performance

Use paginated endpoints for all lists. Balance and analytics endpoints should add cached snapshots once group scale grows. Critical indexes:

- `expenses.group_id`
- `expenses.expense_date`
- `expenses.duplicate_key`
- `expense_splits.group_id`
- `expense_splits.user_id`
- `expense_splits.payer_user_id`
- `settlements.group_id`
- `activity_logs.group_id, created_at`
