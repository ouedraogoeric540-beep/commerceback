<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'reason',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
