<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AdminAuditor
{
    public function __construct(
        private readonly SecurityLogger $security,
    ) {}

    public function record(string $action, ?Model $target = null, array $metadata = []): void
    {
        $request = request();
        $userId = $request->user()?->getKey();

        AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $target !== null ? $target::class : null,
            'target_id' => $target?->getKey(),
            'ip' => $request->ip(),
            'metadata' => SecurityLogger::withoutSecrets($metadata),
            'created_at' => now(),
        ]);

        $this->security->log($action, [
            'target_type' => $target !== null ? $target::class : null,
            'target_id' => $target?->getKey(),
            'user_id' => $userId,
        ] + $metadata);
    }
}
