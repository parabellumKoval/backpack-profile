<?php

namespace Backpack\Profile\app\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Notifications\Notifiable;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Profile\database\factories\ProfileFactory;

class Profile extends Authenticatable
{
    use CrudTrait;
    use Notifiable;
    use HasFactory;

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
      'addresses' => 'array'
    ];

    public static $fields = [
      'firstname' => [
        'rules' => 'required|string|min:2|max:255'
      ],
      
      'lastname' => [
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
        'rules' => 'required|string|min:6|confirmed'
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
    public function toArray() {
      return [
        'id' => $this->id,
        'fullname' => $this->fullname,
        'email' => $this->email,
        'photo' => $this->photo? url($this->photo): null,
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

    public function referrer(){
      return $this->belongsTo(self::class, 'referrer_id', 'id');
    }

    public function referrals(){
      return $this->hasMany(self::class, 'referrer_id', 'id');
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

    public function getFullnameAttribute() {
      return implode(' ', [
        $this->firstname,
        $this->lastname
      ]);
    }

    
    public function setAddressDetailsAttribute($value) {
      $extras = $this->extras;
      $extras['address'] = $value;
      
      $this->extras = $extras;
    }
	
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
	
    // public function getReferralTreeAttribute() {
    //   $referralTree = [];

    //   $referrals = $this->referrals;
      
    //   for($i = 0; $i < config('aimix.account.referral_levels'); $i++) {
    //     foreach($referrals as $key => $referral) {
    //       $referrals = $referral->referrals;

    //       $referralTree
    //     }

    //   }

    //   return $referralTree;
    // }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
}
