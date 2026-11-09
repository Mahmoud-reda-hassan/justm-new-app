<?php

namespace App\Services;

use App\Models\PermissionAudit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PermissionAuditLogger
{
    public function record(string $event, ?User $target = null, array $changes = []): void
    {
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        PermissionAudit::create([
            'actor_user_id' => $actor->id,
            'target_user_id' => $target?->id,
            'event' => $event,
            'changes' => $changes === [] ? null : $changes,
        ]);
    }
}
