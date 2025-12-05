<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use App\Models\User;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\Profile\app\Models\Profile;
use Backpack\Profile\app\Models\Reward;
use Backpack\Profile\app\Models\WalletBalance;
use Backpack\Profile\app\Models\WalletLedger;
use Backpack\Profile\app\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ParabellumKoval\BackpackImages\Exceptions\ImageUploadException;
use ParabellumKoval\BackpackImages\Services\ImageUploader;
use ParabellumKoval\BackpackImages\Support\ImageUploadOptions;
use Throwable;

class ProfileCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function __construct(protected ImageUploader $imageUploader)
    {
        parent::__construct();
    }

    public function setup(): void
    {
        $this->crud->setModel(Profile::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/profile');
        $this->crud->setEntityNameStrings('профиль', 'профили');
        $this->crud->allowAccess('details_row');
        $this->crud->enableDetailsRow();
        $this->crud->setShowView('crud::profile_show');
    }

    protected function setupListOperation(): void
    {
        $this->crud->addColumn([
            'name' => 'fullname',
            'label' => 'ФИО',
        ]);

        $this->crud->addColumn([
            'name' => 'country_code',
            'label' => 'Страна',
            'type' => 'flag',
        ]);

        $this->crud->addColumn([
            'name' => 'user_id',
            'label' => 'Пользователь',
            'type' => 'user_card',
            'user_model' => User::class,
        ]);

        $this->crud->addColumn([
            'name' => 'referral_code',
            'label' => 'Реферальный код',
        ]);

        $this->crud->addColumn([
            'name' => 'referrer',
            'label' => 'Спонсор',
            'type' => 'relationship',
            'entity' => 'referrer',
            'attribute' => 'email',
            'model' => Profile::class,
        ]);

        $this->crud->addColumn([
            'name' => 'balanceHtml',
            'label' => 'Баланс',
            'escaped' => false,
        ]);

        $this->crud->addColumn([
            'name' => 'discount_percent',
            'label' => 'Скидка',
            'type' => 'number',
            'decimals' => 2,
            'suffix' => ' %',
        ]);
    }

    protected function setupCreateOperation(): void
    {
        $this->addProfileFields();
    }

    protected function setupUpdateOperation(): void
    {
        $profile = $this->resolveCurrentProfile();
        $this->addProfileFields($profile);
    }

    protected function addProfileFields(?Profile $profile = null): void
    {
        $billing = Profile::fillAddress($profile?->getMetaSection('billing'));
        $shipping = Profile::fillAddress($profile?->getMetaSection('shipping'));
        $avatar = $profile?->avatarUrl();

        $this->crud->addFields([
            [
                'name' => 'user[name]',
                'label' => 'Имя пользователя',
                'type' => 'text',
                'tab' => 'Аккаунт',
                'value' => $profile?->user?->name,
            ],
            [
                'name' => 'user[email]',
                'label' => 'Email',
                'type' => 'email',
                'tab' => 'Аккаунт',
                'value' => $profile?->user?->email,
            ],
            [
                'name' => 'user[password]',
                'label' => $profile ? 'Новый пароль' : 'Пароль',
                'type' => 'password',
                'tab' => 'Аккаунт',
                'hint' => $profile ? 'Оставьте пустым, чтобы не изменять пароль.' : null,
            ],
            [
                'name' => 'profile[first_name]',
                'label' => 'Имя',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->first_name,
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[last_name]',
                'label' => 'Фамилия',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->last_name,
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[phone]',
                'label' => 'Телефон',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->phone,
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[country_code]',
                'label' => 'Код страны',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->country_code,
                'attributes' => ['maxlength' => 2],
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[locale]',
                'label' => 'Язык',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->locale,
                'attributes' => ['maxlength' => 8],
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[timezone]',
                'label' => 'Часовой пояс',
                'type' => 'text',
                'tab' => 'Профиль',
                'value' => $profile?->timezone,
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'profile[discount_percent]',
                'label' => 'Скидка, %',
                'type' => 'number',
                'tab' => 'Профиль',
                'value' => $profile?->discount_percent,
                'attributes' => [
                    'step' => '0.01',
                    'min' => 0,
                    'max' => 100,
                ],
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'avatar',
                'label' => 'Аватар',
                'type' => 'image',
                'tab' => 'Профиль',
                'value' => $avatar,
                'aspect_ratio' => 1,
                'crop' => false,
            ],
            [
                'name' => 'billing',
                'label' => 'Платёжные данные',
                'type' => 'address_block',
                'tab' => 'Billing',
                'fields' => $this->addressFieldDefinitions(),
                'value' => $billing,
            ],
            [
                'name' => 'shipping',
                'label' => 'Доставка',
                'type' => 'address_block',
                'tab' => 'Shipping',
                'fields' => $this->addressFieldDefinitions(),
                'value' => $shipping,
            ],
        ]);
    }

    public function store()
    {
        $this->crud->hasAccessOrFail('create');

        $payload = $this->validatePayload(false);

        try {
            $profile = DB::transaction(function () use ($payload) {
                $user = new User();
                $user->name = $payload['user']['name'];
                $user->email = $payload['user']['email'];
                $user->password = Hash::make($payload['user']['password']);
                $user->email_verified_at = now();
                $user->save();

                $user->refresh();
                $profile = $user->profile;

                if (!$profile) {
                    $profile = new Profile();
                    $profile->user_id = $user->id;
                }

                if (!$profile->referral_code) {
                    $profile->referral_code = app('backpack.profile.profile_factory')->generateUniqueCode();
                }

                $this->fillProfile($profile, $payload);
                $profile->save();

                return $profile->fresh(['user']);
            });
        } catch (Throwable $throwable) {
            report($throwable);
            \Alert::error('Не удалось создать профиль.')->flash();

            return back()->withInput();
        }

        $this->data['entry'] = $this->crud->entry = $profile;

        \Alert::success(trans('backpack::crud.insert_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($profile->getKey());
    }

    public function update()
    {
        $this->crud->hasAccessOrFail('update');

        $profile = $this->resolveCurrentProfile();

        if (!$profile) {
            abort(404);
        }

        $payload = $this->validatePayload(true, $profile);

        try {
            $profile = DB::transaction(function () use ($profile, $payload) {
                $user = $profile->user ?? new User();
                $user->name = $payload['user']['name'];
                $user->email = $payload['user']['email'];

                if (!empty($payload['user']['password'])) {
                    $user->password = Hash::make($payload['user']['password']);
                }

                if (!$user->exists) {
                    $user->id = $profile->user_id;
                    $user->password = $user->password ?? Hash::make(Str::random(16));
                }

                $user->save();

                $this->fillProfile($profile, $payload);
                $profile->save();

                return $profile->fresh(['user']);
            });
        } catch (Throwable $throwable) {
            report($throwable);
            \Alert::error('Не удалось обновить профиль.')->flash();

            return back()->withInput();
        }

        $this->data['entry'] = $this->crud->entry = $profile;

        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction();

        return $this->crud->performSaveAction($profile->getKey());
    }

    protected function fillProfile(Profile $profile, array $payload): void
    {
        $profileData = $payload['profile'];

        $profile->first_name = $profileData['first_name'] ?? null;
        $profile->last_name = $profileData['last_name'] ?? null;
        $profile->phone = $profileData['phone'] ?? null;
        $profile->country_code = $profileData['country_code'] ?? null;
        $profile->locale = $profileData['locale'] ?? null;
        $profile->timezone = $profileData['timezone'] ?? null;

        if (array_key_exists('discount_percent', $profileData)) {
            $profile->discount_percent = $profileData['discount_percent'] ?? 0;
        }

        $fullName = trim(collect([$profile->first_name, $profile->last_name])->filter()->implode(' '));
        $profile->full_name = $fullName !== '' ? $fullName : null;

        $profile->billing = $payload['billing'];
        $profile->shipping = $payload['shipping'];

        $this->handleAvatarUpload($profile, $payload['avatar']);
    }

    protected function handleAvatarUpload(Profile $profile, ?string $avatar): void
    {
        if ($avatar === null) {
            return;
        }

        $avatar = trim($avatar);

        if ($avatar === '') {
            $profile->avatar_url = null;
            $this->clearAvatarMeta($profile);

            return;
        }

        if (Str::startsWith($avatar, 'data:image')) {
            try {
                $stored = $this->imageUploader->uploadFromBase64($avatar, new ImageUploadOptions(folder: 'avatars'));
                $profile->avatar_url = $stored->url;
                $this->setAvatarMeta($profile, [
                    'avatar_source' => 'admin-upload',
                    'avatar_path' => $stored->path,
                ]);
            } catch (ImageUploadException $exception) {
                Log::warning('Ошибка загрузки аватара: '.$exception->getMessage());
            }

            return;
        }

        if ($avatar !== $profile->avatar_url) {
            $profile->avatar_url = $avatar;
            $this->setAvatarMeta($profile, [
                'avatar_source' => $avatar,
            ]);
        }
    }

    protected function setAvatarMeta(Profile $profile, array $values): void
    {
        $other = $profile->getMetaOther();
        $other = array_merge($other, array_filter($values, fn ($value) => $value !== null && $value !== ''));

        $profile->mergeMeta(['other' => $other]);
    }

    protected function clearAvatarMeta(Profile $profile): void
    {
        $other = $profile->getMetaOther();
        unset($other['avatar_source'], $other['avatar_path']);

        $profile->mergeMeta(['other' => $other]);
    }

    protected function validatePayload(bool $isUpdate, ?Profile $profile = null): array
    {
        $request = $this->crud->getRequest();

        $rules = [
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($profile?->user_id),
            ],
            'user.password' => array_filter([$isUpdate ? 'nullable' : 'required', 'string', 'min:8']),
            'profile.first_name' => ['nullable', 'string', 'max:255'],
            'profile.last_name' => ['nullable', 'string', 'max:255'],
            'profile.phone' => ['nullable', 'string', 'max:255'],
            'profile.country_code' => ['nullable', 'string', 'size:2'],
            'profile.locale' => ['nullable', 'string', 'max:8'],
            'profile.timezone' => ['nullable', 'string', 'max:64'],
            'profile.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'avatar' => ['nullable', 'string'],
        ];

        $rules = array_merge(
            $rules,
            $this->addressValidationRules('billing'),
            $this->addressValidationRules('shipping')
        );

        $validator = Validator::make($request->all(), $rules, [], $this->validationAttributes());
        $data = $validator->validate();

        $userData = $this->sanitizeArray($data['user']);
        $profileData = $this->sanitizeArray($data['profile'] ?? []);
        $billing = $this->sanitizeArray($data['billing'] ?? []);
        $shipping = $this->sanitizeArray($data['shipping'] ?? []);
        $avatar = isset($data['avatar']) ? trim((string) $data['avatar']) : null;

        if (!empty($profileData['country_code'])) {
            $profileData['country_code'] = strtoupper($profileData['country_code']);
        }

        if (!empty($profileData['locale'])) {
            $profileData['locale'] = strtolower($profileData['locale']);
        }

        if (array_key_exists('discount_percent', $profileData)) {
            $profileData['discount_percent'] = $profileData['discount_percent'] !== null
                ? (float) $profileData['discount_percent']
                : null;
        }

        $userData['email'] = strtolower($userData['email']);

        return [
            'user' => $userData,
            'profile' => $profileData,
            'billing' => $billing,
            'shipping' => $shipping,
            'avatar' => $avatar,
        ];
    }

    protected function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        return $data;
    }

    protected function addressValidationRules(string $prefix): array
    {
        return [
            $prefix => ['array'],
            "{$prefix}.email" => ['nullable', 'email', 'max:255'],
            "{$prefix}.phone" => ['nullable', 'string', 'max:255'],
            "{$prefix}.address_1" => ['nullable', 'string', 'max:255'],
            "{$prefix}.postcode" => ['nullable', 'string', 'max:255'],
            "{$prefix}.city" => ['nullable', 'string', 'max:255'],
            "{$prefix}.state" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country" => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return array_merge(
            [
                'user.name' => 'Имя пользователя',
                'user.email' => 'Email',
                'user.password' => 'Пароль',
                'profile.first_name' => 'Имя',
                'profile.last_name' => 'Фамилия',
                'profile.phone' => 'Телефон',
                'profile.country_code' => 'Код страны',
                'profile.locale' => 'Язык',
                'profile.timezone' => 'Часовой пояс',
                'profile.discount_percent' => 'Скидка',
                'avatar' => 'Аватар',
            ],
            $this->addressAttributeLabels('billing', 'Billing'),
            $this->addressAttributeLabels('shipping', 'Shipping')
        );
    }

    protected function addressAttributeLabels(string $prefix, string $title): array
    {
        $fields = [
            'email' => 'Email',
            'phone' => 'Телефон',
            'address_1' => 'Адрес 1',
            'postcode' => 'Индекс',
            'city' => 'Город',
            'state' => 'Регион',
            'country' => 'Страна',
        ];

        $labels = [];

        foreach ($fields as $key => $label) {
            $labels["{$prefix}.{$key}"] = "{$title}: {$label}";
        }

        return $labels;
    }

    protected function resolveCurrentProfile(): ?Profile
    {
        $id = $this->crud->getCurrentEntryId()
            ?? request()->route('id')
            ?? $this->crud->getRequest()->get($this->crud->getModel()->getKeyName());

        if (!$id) {
            return null;
        }

        return Profile::with('user')->find($id);
    }

    public function show($id)
    {
        $this->crud->hasAccessOrFail('show');

        $id = $this->crud->getCurrentEntryId() ?? $id;

        $profile = Profile::with(['user.walletBalance', 'referrer.user'])->findOrFail($id);
        $user = $profile->user;

        $walletBalances = $user
            ? WalletBalance::query()->where('user_id', $user->id)->orderBy('currency')->get()
            : collect();

        $rewards = $user
            ? Reward::query()->where('beneficiary_user_id', $user->id)->orderByDesc('id')->limit(10)->get()
            : collect();

        $ledger = $user
            ? WalletLedger::query()->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get()
            : collect();

        $withdrawals = $user
            ? WithdrawalRequest::query()->where('user_id', $user->id)->orderByDesc('id')->limit(10)->get()
            : collect();

        $this->data = array_merge($this->data, [
            'crud' => $this->crud,
            'title' => $this->crud->getTitle() ?? trans('backpack::crud.preview').' '.$this->crud->entity_name,
            'entry' => $profile,
            'profile' => $profile,
            'user' => $user,
            'walletBalances' => $walletBalances,
            'rewards' => $rewards,
            'ledger' => $ledger,
            'withdrawals' => $withdrawals,
            'billing' => Profile::fillAddress($profile->getMetaSection('billing')),
            'shipping' => Profile::fillAddress($profile->getMetaSection('shipping')),
            'meta' => $profile->metaWithoutOther(),
        ]);

        return view($this->crud->getShowView(), $this->data);
    }

    protected function addressFieldDefinitions(): array
    {
        return [
            [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'wrapper' => ['class' => 'form-group col-md-6'],
            ],
            [
                'name' => 'phone',
                'label' => 'Телефон',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-6'],
            ],
            [
                'name' => 'address_1',
                'label' => 'Адрес 1',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-6'],
            ],
            [
                'name' => 'city',
                'label' => 'Город',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'state',
                'label' => 'Регион',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'postcode',
                'label' => 'Индекс',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
                'name' => 'country',
                'label' => 'Страна',
                'type' => 'text',
                'wrapper' => ['class' => 'form-group col-md-6'],
            ],
        ];
    }

    protected function showDetailsRow($id)
    {
        /** @var Profile $profile */
        $profile = $this->crud->getEntry($id);
        $profile->loadMissing($this->getReferralEagerLoads());

        $referralTree = $this->buildReferralTree($profile);
        $summary = $this->summarizeReferralTree($referralTree);

        return view('crud::details_row', [
            'profile' => $profile,
            'referralTree' => $referralTree,
            'summary' => $summary,
        ]);
    }

    protected function getReferralEagerLoads(): array
    {
        $levels = max(1, (int) config('backpack.profile.referral_levels', 3));
        $relations = ['referrer.user.walletBalance', 'user.walletBalance'];

        $path = 'referrals';
        for ($level = 1; $level <= $levels; $level++) {
            $relations[] = $path;
            $relations[] = $path . '.user.walletBalance';

            if ($level < $levels) {
                $path .= '.referrals';
            }
        }

        return array_values(array_unique($relations));
    }

    protected function buildReferralTree(Profile $profile, array &$visited = []): array
    {
        $profileKey = $profile->getKey();
        if ($profileKey === null) {
            return [];
        }

        if (isset($visited[$profileKey])) {
            return [];
        }

        $visited[$profileKey] = true;

        $profile->loadMissing(['referrals.user.walletBalance']);

        return $profile->referrals->map(function (Profile $referral) use (&$visited) {
            return [
                'profile' => $referral,
                'children' => $this->buildReferralTree($referral, $visited),
            ];
        })->all();
    }

    protected function summarizeReferralTree(array $tree): array
    {
        $levels = [];
        $total = $this->tallyReferralTree($tree, 1, $levels);

        ksort($levels);

        return [
            'total' => $total,
            'levels' => $levels,
        ];
    }

    protected function tallyReferralTree(array $tree, int $level, array &$levels): int
    {
        $count = 0;

        foreach ($tree as $node) {
            $count++;
            $levels[$level] = ($levels[$level] ?? 0) + 1;
            $count += $this->tallyReferralTree($node['children'], $level + 1, $levels);
        }

        return $count;
    }
}
