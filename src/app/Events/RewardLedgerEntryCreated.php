<?php

namespace Backpack\Profile\app\Events;

use Backpack\Profile\app\Models\Reward;
use Backpack\Profile\app\Models\RewardEvent;
use Backpack\Profile\app\Models\WalletLedger;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RewardLedgerEntryCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly RewardEvent $event,
        public readonly Reward $reward,
        public readonly WalletLedger $ledger
    ) {
    }
}

