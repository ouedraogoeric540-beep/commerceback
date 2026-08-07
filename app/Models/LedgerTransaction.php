<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'status',
        'description',
        'idempotency_key',
    ];

    public function entries()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
