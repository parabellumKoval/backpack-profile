<?php
namespace Backpack\Profile\app\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Backpack\Profile\app\Services\ReferralService;

class RetryRewardEvents extends Command
{
    protected $signature = 'profile:rewards:retry {--status=failed} {--limit=100}';
    protected $description = 'Повторно обработать reward events (failed|pending)';

    public function handle(ReferralService $service)
    {
        $status = $this->option('status');
        $limit  = (int)$this->option('limit');

        $ids = DB::table('ak_reward_events')
            ->where('status', $status)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $this->info("Retry event #{$id}");
            $service->process((int)$id);
        }

        $this->info("Done. Retried ".$ids->count()." events.");
    }
}
