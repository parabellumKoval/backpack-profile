<?php
// src/app/Http/Controllers/Admin/WalletLedgerCrudController.php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;

class WalletLedgerCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel(\Backpack\Profile\app\Models\WalletLedger::class);
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin').'/wallet-ledger');
        $this->crud->setEntityNameStrings('ledger record','wallet ledger');

        $this->crud->denyAccess(['create','update','delete']);
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns([
            ['name'=>'id','type'=>'number','label'=>'ID'],
            ['name'=>'created_at','type'=>'datetime','label'=>'Created'],
            [
                'name'       => 'user_id',
                'label'      => 'User',
                'type'       => 'user_card',
                'user_model' => config('backpack.profile.user_model', \App\Models\User::class),
            ],
            ['name'=>'reference_type','type'=>'text','label'=>'Ref. type'],
            ['name'=>'reference_id','type'=>'text','label'=>'Ref. id'],
            ['name'=>'typeHtml','label'=>'Type','escaped' => false,'limit' => 5500], // credit|debit|hold|release|capture
            ['name'=>'amountHtml','label'=>'Amount','escaped' => false,'limit' => 5500],
        ]);

        // фильтры
        $this->crud->addFilter(['type'=>'dropdown','name'=>'type','label'=>'Type'], [
            'credit'=>'credit','debit'=>'debit','hold'=>'hold','release'=>'release','capture'=>'capture'
        ], function ($val){ $this->crud->addClause('where','type',$val); });

        $this->crud->addFilter(['type'=>'text','name'=>'currency','label'=>'Currency'], false,
            function ($val){ $this->crud->addClause('where','currency','like',"%$val%"); });

        $this->crud->addFilter(['type'=>'text','name'=>'reference_type','label'=>'Ref. type'], false,
            function ($val){ $this->crud->addClause('where','reference_type','like',"%$val%"); });

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
