<?php

namespace App\Services\Admin;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AdminAuditor
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, string $message, ?User $subject = null, array $properties = []): AdminActivityLog
    {
        return AdminActivityLog::query()->create([
            'actor_id' => Auth::id(),
            'subject_user_id' => $subject?->id,
            'action' => $action,
            'message' => $message,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => Request::ip(),
        ]);
    }
}
