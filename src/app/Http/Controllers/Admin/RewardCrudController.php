<?php
// src/app/Http/Controllers/Admin/RewardCrudController.php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;

class RewardCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel(\Backpack\Profile\app\Models\Reward::class);
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin').'/rewards');
        $this->crud->setEntityNameStrings('reward','rewards');

        $this->crud->denyAccess(['create','update','delete']);
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns([
            ['name'=>'id','type'=>'number','label'=>'ID'],
            ['name'=>'created_at','type'=>'datetime','label'=>'Created'],
            [
                'name'       => 'beneficiary_user_id',
                'label'      => 'User',
                'type'       => 'user_card',
                'user_model' => config('backpack.profile.user_model', \App\Models\User::class),
            ],
            ['name'=>'event_id','type'=>'number','label'=>'Event ID'],
            ['name'=>'baseAmountHtml','label'=>'Base amount', 'escaped' => false,'limit' => 5500],
            ['name'=>'beneficiary_type','type'=>'text','label'=>'Type'], // actor|upline
            ['name'=>'level','type'=>'number','label'=>'Level'],
            ['name'=>'amountHtml', 'label'=>'Amount', 'escaped' => false,'limit' => 5500],
        ]);

        // фильтры
        $this->crud->addFilter(['type'=>'dropdown','name'=>'beneficiary_type','label'=>'Type'], [
            'actor'=>'actor','upline'=>'upline'
        ], function ($val){ $this->crud->addClause('where','beneficiary_type',$val); });

        $this->crud->addFilter(['type'=>'text','name'=>'currency','label'=>'Currency'], false,
            function ($val){ $this->crud->addClause('where','currency','like',"%$val%"); });

        $this->crud->addFilter(['type'=>'range','name'=>'amount','label'=>'Amount'], [
            'min'=>0,'max'=>100000,'step'=>0.01
        ], function ($val){
            $v = json_decode($val, true);
            if (isset($v['from'])) $this->crud->addClause('where','amount','>=',$v['from']);
            if (isset($v['to']))   $this->crud->addClause('where','amount','<=',$v['to']);
        });

        $this->crud->addFilter(['type'=>'date_range','name'=>'created_at','label'=>'Created at'], false,
            function ($val){
                $d = json_decode($val, true);
                $this->crud->addClause('whereDate','created_at','>=',$d['from']);
                $this->crud->addClause('whereDate','created_at','<=',$d['to']);
            });
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        $this->crud->addColumn(['name'=>'meta','type'=>'textarea','label'=>'Meta (JSON)']);
    }
}
