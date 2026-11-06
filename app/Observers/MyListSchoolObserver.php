<?php

namespace App\Observers;

use App\Jobs\SyncMyListSchoolTagsToGhl;
use App\Models\MyListSchool;

class MyListSchoolObserver
{
    public function created(MyListSchool $membership): void
    {
        SyncMyListSchoolTagsToGhl::dispatch(
            (int) $membership->my_list_id,
            (int) $membership->school_id,
        )->afterCommit();
    }
}
