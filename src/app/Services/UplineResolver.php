<?php
namespace Backpack\Profile\app\Services;

use Illuminate\Support\Facades\DB;

class UplineResolver
{
    /** @return array<int,int> [level => user_id] */
    public function forUser(?int $userId, int $maxLevels): array
    {
        $out = [];
        $cur = $userId;
        for ($lvl=1; $lvl<=$maxLevels && $cur; $lvl++) {
            $sponsor = DB::table('ak_profiles')
                ->where('user_id',$cur)
                ->value('sponsor_profile_id'); // или sponsor_user_id, см. твои поля
            if (!$sponsor) break;
            $sponsorUserId = DB::table('ak_profiles')->where('id',$sponsor)->value('user_id');
            if (!$sponsorUserId) break;
            $out[$lvl] = (int)$sponsorUserId;
            $cur = $sponsorUserId;
        }
        return $out;
    }
}
