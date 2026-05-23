<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Expense;
use App\Models\Transaction;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'google_id',
        'first_name', 
        'last_name', 
        'name',
        'email', 
        'phone', 
        'dob', 
        'gender', 
        'password', 
        'profile_image',
        'avatar',
        'currency',
        'pin_code',
        'pin',
        'is_active'
    ];

    protected $table='users';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'pin_code',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
    public function getProfileImageUrlAttribute()
    {
        // return $this->profile_image ? asset('storage/' . $this->profile_image) : null;
         if ($value) {
            return url('storage/' . $value); // full URL
        }
        return url('default-profile.png');
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function creditCards()
    {
        return $this->hasMany(CreditCard::class);
    }

    public function debitCards()
    {
        return $this->hasMany(DebitCard::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function splitExpenses()
    {
        return $this->hasMany(SplitExpense::class);
    }

    public function expenseSuggestions()
    {
        return $this->hasMany(ExpenseSuggestion::class);

        }
    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new class($token) extends ResetPasswordNotification {
            public function toMail($notifiable)
            {
                return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject('Reset Password Request')
                    ->line('You requested a password reset.')
                    ->line('Your reset token is: ' . $this->token) // send token instead of link
                    ->line('Use this token in the app to reset your password.')
                    ->line('If you did not request this, ignore this email.');
            }
        });
    }

    public function getTotalBalanceAttribute()
    {
        if (Schema::hasTable('wallets')) {
            return (float) $this->wallets()->sum('balance');
        }

        $credits = $this->transactions()->where('type', Transaction::TYPE_CREDIT)->sum('amount');
        $debits = $this->transactions()->where('type', Transaction::TYPE_DEBIT)->sum('amount');
        
        return $credits - $debits;
    }

    public function defaultWallet()
    {
        if (!Schema::hasTable('wallets')) {
            return null;
        }

        return $this->wallets()->where('is_default', true)->first()
            ?? $this->wallets()->first();
    }

    public function totalBalance()
    {
        return (float) $this->total_balance;
    }

    public function getMonthlyExpensesAttribute()
    {
        return $this->expenses()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');
    }

    public function getBudgetUtilizationPercentageAttribute()
    {
        if (!$this->monthly_budget) {
            return 0;
        }
        
        return min(100, ($this->monthly_expenses / $this->monthly_budget) * 100);
    }

    public function getNameAttribute()
    {
        if (!empty($this->attributes['name'])) {
            return $this->attributes['name'];
        }
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
    public function incomes()
    {
        return $this->hasMany(Income::class);
    }

    /**
     * Get all AA consents for this user
     */
    public function aaConsents()
    {
        return $this->hasMany(AaConsent::class);
    }

    /**
     * Get all AA accounts for this user
     */
    public function aaAccounts()
    {
        return $this->hasMany(AaAccount::class);
    }

    /**
     * Get all AA transactions for this user
     */
    public function aaTransactions()
    {
        return $this->hasMany(AaTransaction::class);
    }

    /**
     * Get active AA consents
     */
    public function activeAaConsents()
    {
        return $this->hasMany(AaConsent::class)->active();
    }

}
