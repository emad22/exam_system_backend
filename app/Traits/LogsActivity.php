<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->recordActivity('created');
        });

        static::updated(function ($model) {
            $model->recordActivity('updated');
        });

        static::deleted(function ($model) {
            $model->recordActivity('deleted');
        });
    }

    protected function recordActivity($action)
    {
        $changes = null;
        if ($action === 'updated') {
            $changes = [
                'old' => array_intersect_key($this->getOriginal(), $this->getDirty()),
                'new' => $this->getDirty(),
            ];
            
            // Remove timestamps from changes
            unset($changes['old']['updated_at'], $changes['new']['updated_at']);
            
            // If no real changes after filtering, don't log
            if (empty($changes['new'])) return;
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'description' => $this->getActivityDescription($action),
            'changes' => $changes,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    protected function getActivityDescription($action)
    {
        $modelName = class_basename($this);
        return "{$action} {$modelName} #" . ($this->id ?? 'new');
    }
}
