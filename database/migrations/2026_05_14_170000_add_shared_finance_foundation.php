<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendGroups();
        $this->extendGroupMembers();
        $this->extendExpenses();
        $this->extendExpenseSplits();
        $this->extendSettlements();
        $this->createFriends();
        $this->createDeviceContacts();
        $this->createRecurringSharedExpenses();
        $this->createExpenseComments();
        $this->createActivityLogs();
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('expense_comments');
        Schema::dropIfExists('recurring_shared_expenses');
        Schema::dropIfExists('device_contacts');
        Schema::dropIfExists('friends');
    }

    private function extendGroups(): void
    {
        if (!Schema::hasTable('expense_groups')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expense_groups MODIFY type VARCHAR(50) NOT NULL DEFAULT 'custom'");
        }

        Schema::table('expense_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_groups', 'image_path')) {
                $table->string('image_path')->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('expense_groups', 'permissions')) {
                $table->json('permissions')->nullable()->after('currency');
            }
            if (!Schema::hasColumn('expense_groups', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('is_active');
            }
        });
    }

    private function extendGroupMembers(): void
    {
        if (!Schema::hasTable('group_members')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'status')) {
                $table->string('status', 30)->default('active')->after('role');
            }
            if (!Schema::hasColumn('group_members', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('group_members', 'permissions')) {
                $table->json('permissions')->nullable()->after('invited_by');
            }
            if (!Schema::hasColumn('group_members', 'left_at')) {
                $table->timestamp('left_at')->nullable()->after('joined_at');
            }
        });
    }

    private function extendExpenses(): void
    {
        if (!Schema::hasTable('expenses')) {
            return;
        }

        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'group_id')) {
                $table->foreignId('group_id')->nullable()->after('user_id')->constrained('expense_groups')->nullOnDelete();
            }
            if (!Schema::hasColumn('expenses', 'linked_transaction_id')) {
                $column = $table->foreignId('linked_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                if (Schema::hasColumn('expenses', 'group_id')) {
                    $column->after('group_id');
                }
            }
            if (!Schema::hasColumn('expenses', 'duplicate_key')) {
                $column = $table->string('duplicate_key', 191)->nullable();
                if (Schema::hasColumn('expenses', 'linked_transaction_id')) {
                    $column->after('linked_transaction_id');
                }
                $table->index('duplicate_key');
            }
            if (!Schema::hasColumn('expenses', 'shared_metadata')) {
                $column = $table->json('shared_metadata')->nullable();
                if (Schema::hasColumn('expenses', 'metadata')) {
                    $column->after('metadata');
                }
            }
        });
    }

    private function extendExpenseSplits(): void
    {
        if (!Schema::hasTable('expense_splits')) {
            return;
        }

        Schema::table('expense_splits', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_splits', 'expense_id')) {
                $table->foreignId('expense_id')->nullable()->constrained('expenses')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('expense_splits', 'group_id')) {
                $table->foreignId('group_id')->nullable()->constrained('expense_groups')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('expense_splits', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('expense_splits', 'payer_user_id')) {
                $table->foreignId('payer_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('expense_splits', 'amount_owed')) {
                $table->decimal('amount_owed', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('expense_splits', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0);
            }
            if (!Schema::hasColumn('expense_splits', 'shares')) {
                $table->decimal('shares', 10, 4)->nullable();
            }
            if (!Schema::hasColumn('expense_splits', 'percentage')) {
                $table->decimal('percentage', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('expense_splits', 'is_settled')) {
                $table->boolean('is_settled')->default(false);
            }
            if (!Schema::hasColumn('expense_splits', 'split_basis')) {
                $table->json('split_basis')->nullable();
            }
            if (!Schema::hasColumn('expense_splits', 'itemized_details')) {
                $table->json('itemized_details')->nullable();
            }
        });
    }

    private function extendSettlements(): void
    {
        if (!Schema::hasTable('settlements')) {
            return;
        }

        Schema::table('settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('settlements', 'reference_id')) {
                $table->string('reference_id', 191)->nullable()->after('method');
            }
            if (!Schema::hasColumn('settlements', 'metadata')) {
                $table->json('metadata')->nullable()->after('notes');
            }
        });
    }

    private function createFriends(): void
    {
        if (Schema::hasTable('friends')) {
            return;
        }

        Schema::create('friends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('friend_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_favorite')->default(false);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'friend_user_id']);
        });
    }

    private function createDeviceContacts(): void
    {
        if (Schema::hasTable('device_contacts')) {
            return;
        }

        Schema::create('device_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_contact_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->foreignId('matched_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_registered')->default(false);
            $table->boolean('is_invited')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_contact_id']);
        });
    }

    private function createRecurringSharedExpenses(): void
    {
        if (Schema::hasTable('recurring_shared_expenses')) {
            return;
        }

        Schema::create('recurring_shared_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained('expense_groups')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->string('frequency', 30);
            $table->string('split_type', 30)->default('equal');
            $table->json('payers');
            $table->json('participants');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_generated_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('auto_generate')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function createExpenseComments(): void
    {
        if (Schema::hasTable('expense_comments')) {
            return;
        }

        Schema::create('expense_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment')->nullable();
            $table->string('reaction', 50)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function createActivityLogs(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('expense_groups')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->cascadeOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('event', 80)->index();
            $table->string('message')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }
};
