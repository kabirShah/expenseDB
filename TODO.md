# TODO: Database Migration, Rollback, Restructure, Seeder Creation, and Testing

## Migration and Rollback Steps
- [ ] Run `php artisan migrate` to migrate all tables
- [ ] Run `php artisan migrate:rollback --step=100` to rollback all migrations
- [ ] Run `php artisan migrate` again to restructure all tables

## Seeder Creation
- [ ] Create CategoriesSeeder.php for seeding categories table
- [ ] Create CurrenciesSeeder.php for seeding currencies table
- [ ] Create ExpensesCoreSeeder.php for seeding expenses_core table
- [ ] Create ExpenseSharesSeeder.php for seeding expense_shares table
- [ ] Create UserBalancesSeeder.php for seeding user_balances table
- [ ] Create SettlementsSeeder.php for seeding settlements table (with added fields)
- [ ] Create TransactionsLogSeeder.php for seeding transactions_log table
- [ ] Update DatabaseSeeder.php to call all individual seeders

## Testing
- [ ] Run `php artisan db:seed` to seed the database
- [ ] Run `php artisan tinker` or a custom command to verify data insertion in each table
- [ ] Check if data is successfully pushed to DB and tables are populated
