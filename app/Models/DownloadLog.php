<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadLog extends Model
{
    protected $fillable = [
        'download_token_id',
        'ip_address',
        'user_agent'
    ];

    public function token()
    {
        return $this->belongsTo(DownloadToken::class, 'download_token_id');
    }
}
