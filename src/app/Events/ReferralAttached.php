<?php

namespace Backpack\Profile\app\Events;

use Backpack\Profile\app\Models\Profile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferralAttached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Profile $referral,
        public readonly Profile $sponsor
    ) {
    }
}

