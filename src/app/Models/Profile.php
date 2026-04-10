<?php

namespace Backpack\Profile\app\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Backpack\Store\app\Services\Store;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Backpack\Profile\app\Support\ProfileRoles;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Profile\app\Models\WalletBalance;
use Backpack\Profile\database\factories\ProfileFactory;

class Profile extends Authenticatable
{
    use CrudTrait;
    use Notifiable;
    use HasFactory;
    use CanResetPassword;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_profiles';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    protected $dates = [
    ];

    protected $casts = [
      'extras' => 'array',
      'addresses' => 'array',
      'meta' => 'array',
      'discount_percent' => 'float',
    ];

    public const ADDRESS_KEYS = [
        'email',
        'phone',
        'address_1',
        'postcode',
        'city',
        'state',
        'country',
    ];

    public const DELIVERY_ADDRESS_KEYS = [
        'id',
        'title',
        'country',
        'method',
        'settlement',
        'settlementRef',
        'region',
        'area',
        'street',
        'streetRef',
        'type',
        'house',
        'room',
        'zip',
        'warehouse',
        'warehouseRef',
        'fingerprint',
        'created_at',
        'updated_at',
    ];

    public static $fields = [
      'first_name' => [
        'rules' => 'required|string|min:2|max:255'
      ],
      
      'last_name' => [
        'rules' => 'nullable|string|min:2|max:255'
      ],
      
      'phone' => [
        'rules' => 'nullable|string|min:2|max:255'
      ],

      'addresses.*' => [
        'rules' => 'array:country,city,state,street,apartment,zip',
        'country' => [
          'rules' => 'nullable|string|min:2|max:255',
          'store_in' => 'addresses'
        ],
        'city' => [
          'rules' => 'nullable|string|min:2|max:255',
          'store_in' => 'addresses'
        ],
        'state' => [
          'rules' => 'nullable|string|min:2|max:255',
          'store_in' => 'addresses'
        ],
        'street' => [
          'rules' => 'nullable|string|min:2|max:255',
          'store_in' => 'addresses'
        ],
        'apartment' => [
          'rules' => 'nullable|string|min:1|max:255',
          'store_in' => 'addresses'
        ],
        'zip' => [
          'rules' => 'nullable|string|min:6|max:255',
          'store_in' => 'addresses',
        ],
      ],
    ];

    public static $fieldsForRegistration = [
      'firstname' => [
        'rules' => 'required|string|min:2|max:255'
      ],
      
      'lastname' => [
        'rules' => 'required|string|min:2|max:255'
      ],
      
      'email' => [
        'rules' => 'required|string|email|unique:ak_profiles,email'
      ],
      
      'password' => [
        'rules' => 'required|string|min:6|confirmed',
        'hash' => true
      ],
    
      'password_confirmation' => [
        'rules' => 'required|string|min:6',
        'hidden' => true
      ],
      
      'referrer_code' => [
        'rules' => 'nullable|string'
      ]
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function toArray(): array
    {
        $avatar = $this->avatarUrl();

        return [
            'id' => $this->id,
            'name' => $this->fullname,
            'first_name' => $this->first_name ?? $this->firstname,
            'last_name' => $this->last_name ?? $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $avatar,
            'avatar_url' => $avatar,
            'country_code' => $this->country_code,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'referral_code' => $this->referral_code,
            'discount_percent' => $this->discount_percent,
            'personal_discount_percent' => $this->personal_discount_percent,
            'role' => $this->role,
            'role_label' => $this->role_label,
            'role_data' => $this->rolePayload(),
            'billing' => static::fillAddress($this->getMetaSection('billing')),
            'shipping' => static::fillAddress($this->shipping),
            'saved_delivery_addresses' => $this->savedDeliveryAddresses(),
            'storefront' => $this->currentStorefrontCode(),
            'meta' => $this->metaWithoutOther(),
        ];
    }

    public function toOrderArray(): array
    {
        return [
            'firstname' => $this->first_name ?? $this->firstname,
            'lastname' => $this->last_name ?? $this->lastname,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return ProfileFactory::new();
    }

    protected function discountPercent(): Attribute
    {
      return Attribute::make(
        get: fn ($value) => $value !== null ? (float) $value : 0.0,
      );
    }

    protected function personalDiscountPercent(): Attribute
    {
      return Attribute::make(
        get: fn ($value, array $attributes) => isset($attributes['discount_percent'])
          ? (float) $attributes['discount_percent']
          : 0.0,
      );
    }

    /** 
     *  Get validation rules from fields array
     * @param Array|String $fields
     * @return Array
    */
    public static function getRules($fields = null, $type = 'fields') {
      $node = $fields? $fields: static::$$type;

      $rules = [];
      
      if(is_string($node)) {
        return $node;
      }

      if(is_array($node)) {
        
        foreach($node as $field => $value) {
          if(in_array($field, ['store_in']))
            continue;
          
          $selfRules = static::getRules($value);

          if(is_array($selfRules))
            foreach($selfRules as $k => $v) {
              if($k === 'rules') {
                $rules[$field] = $v;
              }else {
                $name = implode('.', [$field, $k]);
                $rules[$name] = $v;
              }
            }
          else
            $rules[$field] = $selfRules;
        }

      }

      return $rules;
    }
    
    /**
     * getFieldKeys
     *
     * @param  mixed $type
     * @return void
     */
    public static function getFieldKeys($type = 'fields') {
      $keys = array_keys(static::$$type);
      $keys = array_map(function($item) {
        return preg_replace('/[\*\.]/u', '', $item);
      }, $keys);

      return $keys;
    }


    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function orders() {
      return $this->hasMany(config('backpack.profile.order_model', 'Backpack\Store\app\Models\Order'));
    }

    public function reviews() {
      return $this->hasMany(config('backpack.profile.review_model', 'Backpack\Reviews\app\Models\Review'));
    }

    public function user(){
      return $this->belongsTo(\Profile::userModel(), 'user_id', 'id');
    }

    public function referrer(){
      return $this->belongsTo(self::class, 'sponsor_profile_id', 'id');
    }

    public function referrals(){
      return $this->hasMany(self::class, 'sponsor_profile_id', 'id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            $this->fullname,
            $this->email,
            $this->phone,
            $this->country_code,
            sprintf('discount: %s%%', $this->discount_percent ?? 0),
            sprintf('role: %s', $this->role_label ?? $this->role ?? '—'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->fullname ?: $this->email,
        ]);

        return $this->formatUniqHtml($headline, [
            $this->email,
            $this->phone,
            $this->country_code,
            sprintf('discount: %s%%', $this->discount_percent ?? 0),
            sprintf('role: %s', $this->role_label ?? $this->role ?? '—'),
        ]);
    }
    
    public function getWalletBalanceAttribute() {
      return optional($this->user)->walletBalance;
    }

    public function getBalanceHtmlAttribute()
    {
        $user = $this->user;
        if (!$user || !$user->walletBalance || $user->walletBalance->balance === 0) {
            return '-';
        }

        return view('crud::columns.price', [
            'price' => $user->walletBalance->balance ?? 0,
            'currency' => currency_label($user->walletBalance->currency)
        ]);
    }

    public function getEmailAttribute() {
      return optional($this->user)->email;
    }

    public function getPhotoAttribute($value)
    {
        if ($value) {
            return $value;
        }

        return $this->attributes['avatar_url'] ?? null;
    }

    public function avatarUrl(): ?string
    {
        $value = $this->attributes['avatar_url'] ?? $this->attributes['photo'] ?? null;

        if (!$value) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', '//'])) {
            return $value;
        }

        return url($value);
    }

    /**
     * getReferralsCountAttribute
     *
     * @return void
     */
    public function getReferralsCountAttribute() {
      if($this->referrals)
        return count($this->referrals);
      else
        return 0;
    }
    
    /**
     * getFullnameAttribute
     *
     * @return void
     */
    public function getFullnameAttribute(): ?string
    {
        $explicit = $this->attributes['full_name'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $first = $this->attributes['first_name'] ?? $this->attributes['firstname'] ?? null;
        $last = $this->attributes['last_name'] ?? $this->attributes['lastname'] ?? null;
        $name = trim(collect([$first, $last])->filter()->implode(' '));

        if ($name !== '') {
            return $name;
        }

        return $this->user->name ?? null;
    }

    public function getFirstnameAttribute(): ?string
    {
        return $this->attributes['first_name'] ?? null;
    }

    public function setFirstnameAttribute($value): void
    {
        $this->attributes['first_name'] = $value;
    }

    public function getLastnameAttribute(): ?string
    {
        return $this->attributes['last_name'] ?? null;
    }

    public function setLastnameAttribute($value): void
    {
        $this->attributes['last_name'] = $value;
    }
      
    
    public function getNameAttribute() {
      return $this->user->name ?? null;
    }
    /**
     * setAddressDetailsAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setAddressDetailsAttribute($value) {
      $extras = $this->extras;
      $extras['address'] = $value;
      
      $this->extras = $extras;
    }
	    
    /**
     * getAddressDetailsAttribute
     *
     * @return void
     */
    public function getAddressDetailsAttribute() {
      if(isset($this->extras['address']))
        return $this->extras['address'];
      else
        return array(
          'is_default' => 1,
          'country' => '',
          'street' => '',
          'apartment' => '',
          'city' => '',
          'state' => '',
          'zip' => ''	
        );
    }
	    
    public function getBillingAttribute($value = null): array
    {
        if (is_array($value)) {
            return $value;
        }

        return $this->getMetaSection('billing');
    }

    public function setBillingAttribute($value): void
    {
        $normalized = static::normalizeAddress(is_array($value) ? $value : []);
        $this->setMetaSection('billing', $normalized);
    }

    public function getShippingAttribute($value = null): array
    {
        if (is_array($value)) {
            return $value;
        }

        $storefront = $this->currentStorefrontCode();
        if ($storefront !== null) {
            $scoped = $this->getMetaSection("shipping_by_storefront.{$storefront}");
            if ($scoped !== []) {
                return $scoped;
            }
        }

        return $this->getMetaSection('shipping');
    }

    public function setShippingAttribute($value): void
    {
        $normalized = static::normalizeAddress(is_array($value) ? $value : []);
        $this->setScopedShippingAddress($normalized, $this->currentStorefrontCode());
    }

    public function roleDefinition(): ?array
    {
        return ProfileRoles::definition($this->role);
    }

    public function getRoleLabelAttribute(): ?string
    {
        $definition = $this->roleDefinition();

        if (isset($definition['label'])) {
            return $definition['label'];
        }

        return $this->role ? Str::headline($this->role) : null;
    }

    public function rolePayload(?string $role = null): array
    {
        $roleKey = $role ?? $this->role;
        if (!$roleKey) {
            return [];
        }

        $extras = is_array($this->extras) ? $this->extras : [];
        $payload = Arr::get($extras, 'role_data.' . $roleKey, []);

        return is_array($payload) ? $payload : [];
    }

    public function setRolePayload(string $role, array $payload): void
    {
        $extras = is_array($this->extras) ? $this->extras : [];
        $roleData = Arr::get($extras, 'role_data', []);
        $roleData[$role] = $payload;
        $extras['role_data'] = $roleData;

        $this->extras = $extras;
    }

    public function getMetaSection(string $section, ?array $default = []): array
    {
        $meta = $this->meta ?? [];
        if (!is_array($meta)) {
            return $default ?? [];
        }

        $value = Arr::get($meta, $section, $default ?? []);

        return is_array($value) ? $value : [];
    }

    public function getMetaOther(): array
    {
        return $this->getMetaSection('other');
    }

    public function metaWithoutOther(): array
    {
        $meta = $this->meta ?? [];

        return is_array($meta) ? Arr::except($meta, ['other']) : [];
    }

    public function mergeMeta(array $values): void
    {
        $meta = $this->meta ?? [];
        $meta = is_array($meta) ? $meta : [];
        $meta = array_replace_recursive($meta, $values);

        $this->meta = $meta ?: null;
    }

    public function currentStorefrontCode(): ?string
    {
        return Store::normalizeStorefrontCode(Store::storefront());
    }

    public function savedDeliveryAddresses(?string $storefront = null): array
    {
        $resolvedStorefront = $this->resolveStorefrontCode($storefront);
        $addresses = $resolvedStorefront !== null
            ? $this->getMetaSection("delivery_addresses_by_storefront.{$resolvedStorefront}")
            : [];

        if ($addresses === []) {
            $addresses = $this->getMetaSection('delivery_addresses');
        }

        return static::normalizeSavedDeliveryAddresses(is_array($addresses) ? $addresses : [], $resolvedStorefront);
    }

    public function setSavedDeliveryAddresses(array $addresses, ?string $storefront = null): void
    {
        $resolvedStorefront = $this->resolveStorefrontCode($storefront);
        $normalized = static::normalizeSavedDeliveryAddresses($addresses, $resolvedStorefront);

        $meta = $this->meta ?? [];
        $meta = is_array($meta) ? $meta : [];

        if ($resolvedStorefront !== null) {
            Arr::set($meta, "delivery_addresses_by_storefront.{$resolvedStorefront}", $normalized);
        } else {
            $meta['delivery_addresses'] = $normalized;
        }

        $this->meta = $meta ?: null;
    }

    public function syncDeliveryAddress(
        array $delivery,
        array $contact = [],
        ?string $storefront = null,
        ?string $country = null
    ): ?array {
        $resolvedStorefront = $this->resolveStorefrontCode($storefront);
        $shippingAddress = static::profileAddressFromDelivery($delivery, $contact, $country);
        if ($shippingAddress !== []) {
            $this->setScopedShippingAddress($shippingAddress, $resolvedStorefront);
        }

        $candidate = static::normalizeSavedDeliveryAddress($delivery, $resolvedStorefront, $country);
        if ($candidate === null) {
            return null;
        }

        $addresses = $this->savedDeliveryAddresses($resolvedStorefront);
        $existing = null;

        foreach ($addresses as $index => $address) {
            if (($address['fingerprint'] ?? null) === ($candidate['fingerprint'] ?? null)) {
                $existing = $address;
                unset($addresses[$index]);
                break;
            }
        }

        $timestamp = now()->toIso8601String();
        $candidate['id'] = $existing['id'] ?? ($candidate['id'] ?? (string) Str::uuid());
        $candidate['created_at'] = $existing['created_at'] ?? $candidate['created_at'] ?? $timestamp;
        $candidate['updated_at'] = $timestamp;

        array_unshift($addresses, $candidate);
        $this->setSavedDeliveryAddresses(array_slice(array_values($addresses), 0, 20), $resolvedStorefront);

        return $candidate;
    }

    public static function normalizeSavedDeliveryAddresses(array $addresses, ?string $storefront = null): array
    {
        $normalized = [];

        foreach ($addresses as $address) {
            $candidate = static::normalizeSavedDeliveryAddress(is_array($address) ? $address : [], $storefront);
            if ($candidate === null) {
                continue;
            }

            $normalized[] = $candidate;
        }

        return array_values($normalized);
    }

    public static function normalizeSavedDeliveryAddress(
        array $address,
        ?string $storefront = null,
        ?string $country = null
    ): ?array {
        $normalized = [];

        foreach (self::DELIVERY_ADDRESS_KEYS as $key) {
            $value = Arr::get($address, $key);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        $normalized['method'] = trim((string) ($normalized['method'] ?? ''));
        $normalized['country'] = strtoupper((string) ($normalized['country'] ?? $country ?? Store::country()));

        if ($normalized['method'] === '') {
            if (!empty($normalized['warehouse'])) {
                $normalized['method'] = 'default_pickup';
            } elseif (!empty($normalized['street'])) {
                $normalized['method'] = 'default_address';
            }
        }

        if ($normalized['method'] === '') {
            return null;
        }

        if (
            empty($normalized['warehouse'])
            && empty($normalized['street'])
            && empty($normalized['settlement'])
            && empty($normalized['zip'])
        ) {
            return null;
        }

        $resolvedStorefront = Store::normalizeStorefrontCode($storefront);

        if (empty($normalized['title'])) {
            $normalized['title'] = static::buildSavedDeliveryAddressTitle($normalized);
        }

        $normalized['fingerprint'] = static::savedDeliveryAddressFingerprint($normalized, $resolvedStorefront);

        return $normalized;
    }

    public static function profileAddressFromDelivery(array $delivery, array $contact = [], ?string $country = null): array
    {
        $addressLine = trim(implode(', ', array_filter([
            Arr::get($delivery, 'warehouse'),
            trim(implode(' ', array_filter([
                Arr::get($delivery, 'street'),
                Arr::get($delivery, 'house'),
                Arr::get($delivery, 'room'),
            ]))),
        ])));

        return static::normalizeAddress([
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'address_1' => $addressLine,
            'postcode' => Arr::get($delivery, 'zip'),
            'city' => Arr::get($delivery, 'settlement'),
            'state' => Arr::get($delivery, 'region') ?? Arr::get($delivery, 'area'),
            'country' => $country ?? Store::country(),
        ]);
    }

    protected function setMetaSection(string $section, array $data): void
    {
        $meta = $this->meta ?? [];
        $meta = is_array($meta) ? $meta : [];

        if ($data === []) {
            unset($meta[$section]);
        } else {
            $meta[$section] = $data;
        }

        $this->meta = $meta ?: null;
    }

    protected function setScopedShippingAddress(array $data, ?string $storefront = null): void
    {
        $normalized = static::normalizeAddress($data);
        $meta = $this->meta ?? [];
        $meta = is_array($meta) ? $meta : [];

        $resolvedStorefront = $this->resolveStorefrontCode($storefront);
        if ($resolvedStorefront !== null) {
            if ($normalized === []) {
                Arr::forget($meta, "shipping_by_storefront.{$resolvedStorefront}");
            } else {
                Arr::set($meta, "shipping_by_storefront.{$resolvedStorefront}", $normalized);
            }
        }

        if ($normalized === []) {
            unset($meta['shipping']);
        } else {
            $meta['shipping'] = $normalized;
        }

        $this->meta = $meta ?: null;
    }

    protected function resolveStorefrontCode(?string $storefront = null): ?string
    {
        if ($storefront !== null && $storefront !== '') {
            return Store::normalizeStorefrontCode($storefront);
        }

        return $this->currentStorefrontCode();
    }

    protected static function buildSavedDeliveryAddressTitle(array $address): string
    {
        $headline = trim(implode(', ', array_filter([
            $address['settlement'] ?? null,
            $address['warehouse'] ?? null,
            trim(implode(' ', array_filter([
                $address['street'] ?? null,
                $address['house'] ?? null,
            ]))),
            $address['zip'] ?? null,
        ])));

        return $headline !== '' ? $headline : Str::headline((string) ($address['method'] ?? 'delivery'));
    }

    protected static function savedDeliveryAddressFingerprint(array $address, ?string $storefront = null): string
    {
        $payload = [
            'storefront' => $storefront,
            'country' => strtoupper((string) ($address['country'] ?? '')),
            'method' => strtolower((string) ($address['method'] ?? '')),
            'settlement' => strtolower((string) ($address['settlement'] ?? '')),
            'region' => strtolower((string) ($address['region'] ?? '')),
            'area' => strtolower((string) ($address['area'] ?? '')),
            'street' => strtolower((string) ($address['street'] ?? '')),
            'house' => strtolower((string) ($address['house'] ?? '')),
            'room' => strtolower((string) ($address['room'] ?? '')),
            'zip' => strtolower((string) ($address['zip'] ?? '')),
            'warehouse' => strtolower((string) ($address['warehouse'] ?? '')),
            'warehouse_ref' => strtolower((string) ($address['warehouseRef'] ?? '')),
        ];

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function normalizeAddress(?array $address): array
    {
        if (!is_array($address)) {
            return [];
        }

        $normalized = [];

        foreach (self::ADDRESS_KEYS as $key) {
            $value = Arr::get($address, $key);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    public static function fillAddress(?array $address): array
    {
        $address = is_array($address) ? $address : [];
        $filled = [];

        foreach (self::ADDRESS_KEYS as $key) {
            $value = Arr::get($address, $key);
            $filled[$key] = $value === null ? '' : (string) $value;
        }

        return $filled;
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}
