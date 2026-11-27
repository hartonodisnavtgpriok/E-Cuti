<?php

namespace App\Domain\Leave;

use Illuminate\Support\Facades\DB;

final class WorkflowLock
{
    public static function acquire(): void
    {
        DB::table('workflow_configuration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
    }
}
