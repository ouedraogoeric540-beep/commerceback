<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'code',
        'currency',
        'owner_type',
        'owner_id',
        'wallet_type',
    ];

    public function owner()
    {
        return $this->morphTo();
    }

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }
    
    // Helper to get current balance based on type
    public function getBalanceAttribute()
    {
        $debits = $this->entries()->where('type', 'debit')->sum('amount');
        $credits = $this->entries()->where('type', 'credit')->sum('amount');

        // Assets and Expenses increase with debits
        if (in_array($this->type, ['asset', 'expense'])) {
            return $debits - $credits;
        }

        // Liabilities, Equity, and Revenue increase with credits
        return $credits - $debits;
    }
}
