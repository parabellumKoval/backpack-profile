<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\Profile\app\Http\Requests\ProfileRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class UsermetaCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ProfileCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel('Backpack\Profile\app\Models\Profile');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/profile');
        $this->crud->setEntityNameStrings('usermeta', 'usermetas');
    }

    protected function setupListOperation()
    {
        
        $this->crud->addColumn([
          'name' => 'photo',
          'label' => 'Фото',
          'type' => 'image',
          'height' => '50px',
          'width'  => '50px',
        ]);

        $this->crud->addColumn([
          'name' => 'id',
          'label' => 'ID',
        ]);
        
        $this->crud->addColumn([
          'name' => 'email',
          'label' => 'Email',
        ]);
        
        $this->crud->addColumn([
          'name' => 'phone',
          'label' => 'Телефон',
        ]);

        // if(config('aimix.account.enable_bonus_system')) {
        //   $this->crud->addColumn([
        //     'name' => 'balance',
        //     'label' => 'Bonus balance',
        //     'type' => 'closure',
        //     'function' => function($entry) {
        //         if(!count($entry->transactions->where('is_completed', 1)))
        //           return;
                  
        //         return '$'.$entry->transactions->where('is_completed', 1)->sortByDesc('created_at')->first()->balance;
        //     }
        //   ]);
        // }
      
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProfileRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        
        $this->crud->addField([
          'name' => 'login',
          'label' => 'Логин',
        ]);

        $this->crud->addField([
          'name' => 'email',
          'label' => 'Email',
        ]);

        $this->crud->addField([
          'name' => 'firstname',
          'label' => 'Имя',
        ]);

        $this->crud->addField([
          'name' => 'lastname',
          'label' => 'Фамилия',
        ]);

        $this->crud->addField([
          'name' => 'phone',
          'label' => 'Телефон',
        ]);

        $this->crud->addField([
          'name'  => 'addresses',
          'label' => 'Адресы',
          'type'  => 'repeatable',
          'fields' => [
            [
              'name' => 'is_default',
              'label' => 'По-умолчанию',
              'type' => 'checkbox',
              'attributes' => [
                'class' => 'check-is_default',
                'onclick' => '
                  return toggle(event);

                  function toggle(event) {
                    const target = event.target

                    document.getElementsByClassName("check-is_default")
                      .forEach(function (item) {
                        if(item !== target) 
                          item.checked = false
                      })
                  };
                '
              ]
            ],
            [
              'name' => 'country',
              'label' => 'Страна',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
            [
              'name' => 'street',
              'label' => 'Улица',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
            [
              'name' => 'apartment',
              'label' => 'Дом/Квартира',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
            [
              'name' => 'city',
              'label' => 'Город',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
            [
              'name' => 'state',
              'label' => 'Страна',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
            [
              'name' => 'zip',
              'label' => 'Индекс',
              'wrapper'   => [ 
                'class' => 'form-group col-md-6'
              ],
            ],
          ],
          'new_item_label'  => 'Добавить адрес',
          'init_rows' => 1,
        ]);
        
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
      $this->crud->setValidation(ProfileRequest::class);

      $this->crud->addColumn([
          'name' => 'user_id',
          'label' => 'User',
          'type' => 'user_link'
        ]);
      
      $this->crud->addColumn([
        'name' => 'firstname',
        'label' => 'Firstname',
      ]);

      $this->crud->addColumn([
        'name' => 'lastname',
        'label' => 'Lastname',
      ]);

      $this->crud->addColumn([
        'name' => 'extras',
        'label' => 'Prefered communication',
        'type' => 'usermeta_extras'
      ]);

      $this->crud->addColumn([
        'name' => 'telephone',
        'label' => 'Telephone',
      ]);

      $this->crud->addColumn([
        'name' => 'patronymic',
        'label' => 'Patronymic',
      ]);
      
      $this->crud->addColumn([
        'name' => 'gender',
        'label' => 'Gender'
      ]);
      
      $this->crud->addColumn([
        'name' => 'birthday',
        'label' => 'Birthday',
      ]);
    
      if(config('aimix.account.enable_referral_system')) {
        $this->crud->addColumn([
          'name' => 'referrer_id',
          'label' => 'Referrer',
          'entity' => 'referrer',
          'attribute' => 'firstname', 
          'model' => 'Aimix\Account\app\Models\Usermeta',
          'type' => 'select',
        ]);

        $this->crud->addColumn([
          'name' => 'referral_code',
          'label' => 'Referral code',
        ]);
        
        $this->crud->addColumn([
          'name' => 'referrals',
          'label' => 'Referrals',
          'type' => 'referrals_info'
        ]);
      }
      
      if(config('aimix.account.enable_bonus_system')) {
        $this->crud->addColumn([
          'name' => 'transactions',
          'label' => 'Transactions',
          'type' => 'transactions_info'
        ]);
      }
    }
}
