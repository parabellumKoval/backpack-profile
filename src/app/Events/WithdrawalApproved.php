<?php

namespace Backpack\Profile\app\Events;

use Backpack\Profile\app\Models\WithdrawalRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WithdrawalRequest $withdrawal
    ) {
    }
}

