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
        // 'bonus_balance' => $this->bonus_balance,
        // 'total_earned_bonuses' => $this->total_earned_bonuses,
        // 'this_month_earned_bonuses' => $this->this_month_earned_bonuses,
        // 'referrals' => $this->referrals->where('is_registred', 1)->toArray(),
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

    public function thisMonthTransactions() {
      return $this->hasMany('Aimix\Account\app\Models\Transaction')->whereMonth('created_at', now()->format('m'));
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
      return $this->firstname . ' ' . $this->lastname;
    }

    public function getBonusBalanceAttribute() {
      $balance = 0;
      
      foreach($this->transactions->where('is_completed', 1)->where('balance', '!==', null) as $transaction) {
        $balance += $transaction->change;
      }

      return round($balance, 2);
    }

    public function getThisMonthEarnedBonusesAttribute() {
      $bonuses = 0;

      foreach($this->thisMonthTransactions->where('is_completed', 1)->where('change', '>', 0) as $transaction) {
        $bonuses += $transaction->change;
      }

      return round($bonuses, 2);
    }

    public function getTotalEarnedBonusesAttribute() {
      $bonuses = 0;

      foreach($this->transactions->where('is_completed', 1)->where('change', '>', 0) as $transaction) {
        $bonuses += $transaction->change;
      }

      return round($bonuses, 2);
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
