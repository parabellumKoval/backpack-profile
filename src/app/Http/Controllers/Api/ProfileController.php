<?php

namespace Backpack\Profile\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Rules\EquallyPassword;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Models\WalletLedger;
use Backpack\Profile\app\Models\RewardEvent;
use Backpack\Profile\app\Models\Reward;
use Backpack\Profile\app\Http\Resources\ProfileFullResource;
use Backpack\Profile\app\Http\Resources\ProfileTinyResource;
use Backpack\Profile\app\Http\Resources\WalletLedgerResource;
use Backpack\Profile\app\Http\Requests\WalletLedgerRequest;

class ProfileController extends \App\Http\Controllers\Controller
{
  
    private $FULL_RESOURCE = '';
    private $TINY_RESOURCE = '';
    private $PROFILE_MODEL = '';

    public function __construct() {
      $this->FULL_RESOURCE = config('backpack.profile.full_resource', 'Backpack\Profile\app\Http\Resources\ProfileFullResource');
      $this->TINY_RESOURCE = config('backpack.profile.tiny_resource', 'Backpack\Profile\app\Http\Resources\ProfileTinyResource');
      $this->PROFILE_MODEL = config('backpack.profile.profile_model', 'Backpack\Profile\app\Models\Profile');
    }

    // public function test(Request $request) {
    //   //return $this->PROFILE_MODEL::getRules();
    //   return $this->update($request);
    // }

    /**
     * Update profile data from request data
     * 
     * @param Request $request
     * @return Backpack/Profile/app/Models/Profile $profile
     */

    public function update(Request $request) {

      // Get user instance from AUTH guard
      $user = Auth::guard('profile')->user();

      $profile = $user->profile;

      if(!$profile)
        return response()->json('Profile not found, access denied', 403);

      // Get only allowed fields
      $data = $request->only($this->PROFILE_MODEL::getFieldKeys());

      // Apply validation rules to data
      $validator = Validator::make($data, $this->PROFILE_MODEL::getRules());
  
      if ($validator->fails()) {
        return response()->json($validator->errors(), 400);
      }

      try {
        foreach($data as $field_name => $field_value){

          $field = $this->PROFILE_MODEL::$fields[$field_name] ?? $this->PROFILE_MODEL::$fields[$field_name.'.*'];
          
          if(isset($field['store_in'])) {
            $field_old_value = $profile->{$field['store_in']};
            $field_old_value[$field_name] = $field_value;
            $profile->{$field['store_in']} = $field_old_value;
          }else {
            $profile->{$field_name} = $field_value;
          }
        }

        $profile->save();
      }catch(\Exception $e){
        return response()->json($e->getMessage(), 400);
      }

      return response()->json($profile);
    }


    public function referrals(Request $request) {
      // $profile = Auth::guard('profile')->user();
      $user = $request->user();

      if($user->profile)
        return response()->json('Profile not found, access denied', 403);

      $referrals = $user->profile->referrals()->paginate(12);
      
      // return $this->TINY_RESOURCE::collection($referrals);
      return response()->json($referrals);
    }

    /**
     * Get wallet ledger history for authenticated user with pagination
     * Includes detailed information about operations: withdrawals, rewards, etc.
     * 
     * @param WalletLedgerRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function walletLedger(WalletLedgerRequest $request) {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Параметры пагинации
        $perPage = $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100); // Ограничиваем от 1 до 100

        // Получаем записи из ledger для текущего пользователя
        $ledgerQuery = WalletLedger::forUser($user->id)->recent();

        // Фильтрация по типу операции
        if ($request->filled('type')) {
            $ledgerQuery->ofType($request->type);
        }

        // Фильтрация по типу ссылки
        if ($request->filled('reference_type')) {
            $ledgerQuery->ofReferenceType($request->reference_type);
        }

        $ledgerEntries = $ledgerQuery->paginate($perPage);

        // Обогащаем данные подробной информацией
        $enrichedEntries = $ledgerEntries->getCollection()->map(function ($entry) {
            // Добавляем подробности операции к модели
            $entry->operation_details = $this->getOperationDetails($entry);
            return $entry;
        });

        // Заменяем коллекцию обогащенными данными
        $ledgerEntries->setCollection($enrichedEntries);

        return WalletLedgerResource::collection($ledgerEntries)->additional([
            'meta' => [
                'pagination' => [
                    'current_page' => $ledgerEntries->currentPage(),
                    'per_page' => $ledgerEntries->perPage(),
                    'total' => $ledgerEntries->total(),
                    'last_page' => $ledgerEntries->lastPage(),
                    'from' => $ledgerEntries->firstItem(),
                    'to' => $ledgerEntries->lastItem(),
                ]
            ]
        ]);
    }

    /**
     * Get detailed information about wallet ledger operation
     * 
     * @param WalletLedger $entry
     * @return array
     */
    private function getOperationDetails(WalletLedger $entry): array
    {
        $details = [
            'description' => $this->getOperationDescription($entry),
            'related_data' => null
        ];

        // Для операций связанных с наградами - добавляем подробности
        if (in_array($entry->reference_type, ['referral_reward', 'review_reward', 'order_reward'])) {
            $details['related_data'] = $this->getRewardDetails($entry);
        }

        // Для операций вывода средств
        if ($entry->reference_type === 'withdrawal') {
            $details['related_data'] = $this->getWithdrawalDetails($entry);
        }

        // Для операций связанных с заказами
        if ($entry->reference_type === 'order') {
            $details['related_data'] = $this->getOrderDetails($entry);
        }

        return $details;
    }

    /**
     * Get human-readable operation description
     * 
     * @param WalletLedger $entry
     * @return string
     */
    private function getOperationDescription(WalletLedger $entry): string
    {
        $descriptions = [
            'credit' => [
                'referral_reward' => 'Вознаграждение по реферальной программе',
                'review_reward' => 'Вознаграждение за отзыв',
                'order_reward' => 'Вознаграждение за заказ',
                'bonus' => 'Бонусное начисление',
                'refund' => 'Возврат средств',
                'default' => 'Пополнение счета'
            ],
            'debit' => [
                'withdrawal' => 'Вывод средств',
                'order' => 'Оплата заказа',
                'fee' => 'Комиссия',
                'default' => 'Списание со счета'
            ],
            'hold' => [
                'withdrawal' => 'Блокировка средств для вывода',
                'order' => 'Блокировка средств для заказа',
                'default' => 'Блокировка средств'
            ],
            'release' => [
                'withdrawal' => 'Разблокировка средств (отмена вывода)',
                'order' => 'Разблокировка средств (отмена заказа)',
                'default' => 'Разблокировка средств'
            ],
            'capture' => [
                'withdrawal' => 'Подтверждение вывода средств',
                'order' => 'Подтверждение оплаты заказа',
                'default' => 'Подтверждение операции'
            ]
        ];

        $typeDescriptions = $descriptions[$entry->type] ?? [];
        
        return $typeDescriptions[$entry->reference_type] ?? 
               $typeDescriptions['default'] ?? 
               ucfirst($entry->type) . ' operation';
    }

    /**
     * Get reward-related details
     * 
     * @param WalletLedger $entry
     * @return array|null
     */
    private function getRewardDetails(WalletLedger $entry): ?array
    {
        // Ищем связанные награды по meta данным или reference_id
        $rewardEvent = null;
        $rewards = collect();

        // Пытаемся найти reward event
        if ($entry->meta && isset($entry->meta['reward_event_id'])) {
            $rewardEvent = RewardEvent::find($entry->meta['reward_event_id']);
        } elseif ($entry->reference_id) {
            // Альтернативный поиск по reference_id (если это ID reward event)
            $rewardEvent = RewardEvent::find($entry->reference_id);
        }

        if ($rewardEvent) {
            $rewards = $rewardEvent->rewards()
                ->where('beneficiary_user_id', $entry->user_id)
                ->get();
        }

        if ($rewardEvent || $rewards->isNotEmpty()) {
            return [
                'trigger' => $rewardEvent?->trigger ?? 'unknown',
                'trigger_label' => $this->getTriggerLabel($rewardEvent?->trigger),
                'event_payload' => $rewardEvent?->payload,
                'rewards_count' => $rewards->count(),
                'total_amount' => $rewards->sum('amount'),
                'currency' => $rewards->first()?->currency,
            ];
        }

        return null;
    }

    /**
     * Get withdrawal-related details
     * 
     * @param WalletLedger $entry
     * @return array|null
     */
    private function getWithdrawalDetails(WalletLedger $entry): ?array
    {
        // Здесь можно добавить логику для получения деталей вывода
        // если у вас есть модель Withdrawal
        return [
            'withdrawal_id' => $entry->reference_id,
            'status' => $entry->type, // hold/capture/release
            'meta' => $entry->meta
        ];
    }

    /**
     * Get order-related details
     * 
     * @param WalletLedger $entry
     * @return array|null
     */
    private function getOrderDetails(WalletLedger $entry): ?array
    {
        // Здесь можно добавить логику для получения деталей заказа
        // если у вас есть модель Order
        return [
            'order_id' => $entry->reference_id,
            'meta' => $entry->meta
        ];
    }

    /**
     * Get human-readable trigger label
     * 
     * @param string|null $trigger
     * @return string
     */
    private function getTriggerLabel(?string $trigger): string
    {
        $labels = [
            'review.published' => 'Опубликованный отзыв',
            'store.order_paid' => 'Оплаченный заказ',
            'referral.signup' => 'Регистрация реферала',
            'referral.purchase' => 'Покупка реферала',
        ];

        return $labels[$trigger] ?? ucfirst(str_replace('.', ' ', $trigger ?? 'unknown'));
    }

    // public function changePassword(Request $request) {
    //   $user = \Auth::user();
    //   $newPass = $request->input('password');
    //   $confirmPass = $request->input('password_confirmation');

    //   $validatedData = $request->validate([
    //       'password' => ['required', 'confirmed']
    //   ]);
      
    //   $user->password = \Hash::make($newPass);
    //   $user->save();

    //   return redirect('account')->with('type', 'success')->with('message', 'Your password has been successfully changed!');
    // }

}
