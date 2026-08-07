<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_type',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime:Y-m-d\TH:i:s.\0\0\0\0\0\0\Z',
    ];

    protected $appends = ['attachment_url'];

    protected $hidden = ['attachment_path'];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_path
            ? asset('storage/' . $this->attachment_path)
            : null;
    }
}
