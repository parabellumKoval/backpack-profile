<?php

namespace Backpack\Profile\app\Services;

use Illuminate\Support\Facades\DB;

class EventCounter {
    public function next(string $subjectType, string $subjectId, string $transition): int {
        $row = DB::table('ak_event_counters')
          ->where(['subject_type'=>$subjectType,'subject_id'=>$subjectId, 'transition'=>$transition])
          ->lockForUpdate()->first();
        if (!$row) {
            DB::table('ak_event_counters')->insert([
                'subject_type'=>$subjectType,'subject_id'=>$subjectId,
                'transition'=>$transition,'version'=>1,'updated_at'=>now(),
            ]);
            return 1;
        }
        DB::table('ak_event_counters')
          ->where('id',$row->id)
          ->update(['version'=>DB::raw('version+1'),'updated_at'=>now()]);
        return $row->version + 1;
    }
}
