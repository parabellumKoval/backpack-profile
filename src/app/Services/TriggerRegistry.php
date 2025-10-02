<?php
namespace Backpack\Profile\app\Services;

use Backpack\Profile\app\Contracts\ReferralTrigger;

class TriggerRegistry
{
    /** @var array<string, class-string<Contracts\ReferralTrigger>> */
    protected array $map = [];

    public function register(string $alias, string $class): void
    {
        $this->map[$alias] = $class;
    }

    public function all(): array { return $this->map; }

    public function make(string $alias): ?ReferralTrigger
    {
        $class = $this->map[$alias] ?? null;
        return $class ? app($class) : null;
    }
}