<?php

namespace App\Services;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperationLogService
{
    public function record(
        User $actor,
        Request $request,
        string $module,
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): OperationLog {
        return OperationLog::create([
            'user_id' => $actor->id,
            'module' => $module,
            'action' => $action,

            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),

            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,

            'ip_address' => $request->ip(),
            'user_agent' => Str::limit(
                (string) $request->userAgent(),
                65535,
                ''
            ),
        ]);
    }
}