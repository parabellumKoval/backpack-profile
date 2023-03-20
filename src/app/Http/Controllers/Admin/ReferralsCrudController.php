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
class ReferralsCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel('Backpack\Profile\app\Models\Profile');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/referrals');
        $this->crud->setEntityNameStrings('referrals', 'referrals');

        $this->crud->addClause('whereHas', 'referrals');

        $this->crud->enableDetailsRow();
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
          'name' => 'fullname',
          'label' => 'Имя',
        ]);
        
        $this->crud->addColumn([
          'name' => 'email',
          'label' => 'Email',
        ]);
        
        $this->crud->addColumn([
          'name' => 'referralsCount',
          'label' => 'Рефералы',
        ]);
      
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProfileRequest::class);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    protected function setupShowOperation()
    {
      $this->crud->setValidation(ProfileRequest::class);
    }

    protected function showDetailsRow($id){
      $user = $this->crud->getEntry($id);

      return view('vendor.backpack.crud.details_row', [
        'user' =>  $user
      ]);
    }
}
