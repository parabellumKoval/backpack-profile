<?php
namespace Backpack\Profile\app\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

use Backpack\Profile\app\Models\RewardEvent;

class Profile {

  public function __construct(protected ReferralService $referrals) {}

  public function trigger(
      string $alias,
      string|null $externalId,
      array $payload = [],
      int|Authenticatable|null $actor = null,
      array $opts = [] // subject_type/id и т.п.
  ): void {
    $externalId = $externalId ?? $this->makeExternalId(
        $opts['subject_type'] ?? null,
        $opts['subject_id']   ?? null,
        $alias
    );


    $this->referrals->record($alias, $externalId, $payload, $actor, $opts['subject_type'] ?? null, $opts['subject_id'] ?? null);
  }

  // Обратная операция trigger
  // public function reverse(string $alias, $externalIdOrNull = null, string $reason='reversal', ?string $subjectType=null, ?string $subjectId=null): ?int
  // {
  //     // $parentId = is_numeric($externalIdOrNull)
  //     //     ? (int)$externalIdOrNull
  //     //     : $this->findReversibleEventId($alias, is_string($externalIdOrNull)?$externalIdOrNull:null, $subjectType, $subjectId);
      
  //     $parentId = $this->findReversibleEventId($alias, $externalIdOrNull, $subjectType, $subjectId);

  //     return $parentId ? $this->referrals->reverseEvent($parentId, $reason) : null;
  // }

  public function reverse(string $alias, $externalIdOrNull = null, string $reason='reversal', ?string $subjectType=null, ?string $subjectId=null): ?int
  {
      // $parentId = is_numeric($externalIdOrNull)
      //     ? (int)$externalIdOrNull
      //     : $this->findReversibleEventId($alias, is_string($externalIdOrNull)?$externalIdOrNull:null, $subjectType, $subjectId);
      
      $parentId = $this->findReversibleEventId($alias, $externalIdOrNull, $subjectType, $subjectId);

      return $parentId ? $this->referrals->reverseEvent($parentId, $reason) : null;
  }

  public function reverseByEventId(int $eventId, string $reason='reversal'): ?int {
      return $this->referrals->reverseEvent($eventId, $reason);
  }

  // 2) Откат по паре (alias + external_id) — когда ты сам контролируешь external_id
  public function reverseByExternal(string $alias, string $externalId, string $reason='reversal'): ?int {
      $event = \DB::table('ak_reward_events')
          ->where(['trigger'=>$alias,'external_id'=>$externalId,'status'=>'processed','is_reversal'=>false])
          ->orderByDesc('id')->first();
      return $event ? $this->referrals->reverseEvent((int)$event->id, $reason) : null;
  }

  // 3) Откат ПО СУБЪЕКТУ (когда у тебя есть review_id, но нет external_id)
  // Найдём ПОСЛЕДНЕЕ processed событие с этим alias и subject_type/id.
  public function reverseLatestForSubject(string $alias, string $subjectType, string $subjectId, string $reason='reversal'): ?int {
      $event = \DB::table('ak_reward_events')
          ->where(['trigger'=>$alias,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'status'=>'processed','is_reversal'=>false])
          ->orderByDesc('id')->first();
      return $event ? $this->referrals->reverseEvent((int)$event->id, $reason) : null;
  }

  public static function currencyOptions($fiat_only = false) {
    $values = \Settings::get('profile.currencies');
    $currencies = array_column($values, 'name', 'code');

    if(\Settings::get('profile.points.enabled') && !$fiat_only) {
      $currencies[\Settings::get('profile.points.key', 'point')] = \Settings::get('profile.points.name', 'point');
    }

    return $currencies;
  }

  public static function currencyValues() {
    $values = \Settings::get('profile.currencies');
    return array_column($values, 'code');
  }

  public static function currencies() {
    return \Settings::get('profile.currencies');
  }

  public function userModel(): string
  {
      return \Settings::get('profile.user_model')
          ?: (config('auth.providers.users.model') ?? \App\Models\User::class);
  }


  // --- helpers ---
  protected function resolveUserId(mixed $user): ?int
  {
      if ($user instanceof Authenticatable) return $user->getAuthIdentifier();
      if (is_numeric($user)) return (int)$user;
      return null;
  }

  protected function makeExternalId(?string $subjectType, ?string $subjectId, string $alias): string
  {
      if ($subjectType && $subjectId) {
          $v = app(\Backpack\Profile\app\Services\EventCounter::class)
                ->next($subjectType, (string)$subjectId, $alias);
          return sprintf('%s:%s:%s:v%d', class_basename($subjectType), $subjectId, str_replace('.','_',$alias), $v);
      }
      // fallback — UUID
      return (string)\Illuminate\Support\Str::uuid();
  }

  protected function findReversibleEventId(string $alias, ?string $externalId, ?string $subjectType, ?string $subjectId): ?int
  {
      $q = \DB::table('ak_reward_events')->where('status','processed')->where('is_reversal', false);

      if ($externalId) {
          $q->where('trigger', $alias)->where('external_id', $externalId);
      } elseif ($subjectType && $subjectId) {
          // берём последний processed по этому субъекту и alias
          $q->where('trigger', $alias)
            ->where('subject_type',$subjectType)
            ->where('subject_id',$subjectId)
            ->orderByDesc('id');
      } else {
          return null;
      }

      $event = $q->first();
      if (!$event) return null;

      // уже сторнировано?
      $already = \DB::table('ak_reward_events')
          ->where('parent_event_id', $event->id)
          ->where('is_reversal', true)
          ->exists();

      return $already ? null : (int)$event->id;
  }
}