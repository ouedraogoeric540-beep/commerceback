<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Record an audit event.
     *
     * @param string $action e.g. "kyc.approved", "user.blocked"
     * @param Model|null $target The Eloquent model being acted upon
     * @param array $metadata Extra context (old values, new values, reason...)
     */
    public static function log(string $action, ?Model $target = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id'   => $target?->getKey(),
            'metadata'    => $metadata,
            'ip_address'  => Request::ip(),
        ]);
    }
}
