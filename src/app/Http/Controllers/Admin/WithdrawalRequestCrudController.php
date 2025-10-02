<?php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Profile\app\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class WithdrawalRequestCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel(WithdrawalRequest::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') .'/withdrawals');
        $this->crud->setEntityNameStrings('withdrawal', 'withdrawals');

        // доступ (опционально)
        // CRUD::denyAccess(['create','update','delete']);
        // мы управляем статусом через кастомные экшены
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns([
          ['name'=>'id','label'=>'ID','type'=>'number'],
          ['name'=>'created_at','label'=>'Created','type'=>'datetime'],
          [
            'name'       => 'user_id',
            'label'      => 'User',
            'type'       => 'user_card',
            'user_model' => config('backpack.profile.user_model', \App\Models\User::class),
          ],
          // курс на момент заявки
        //   ['name'=>'fx_rate','label'=>'FX (1 wallet → payout)','type'=>'number','decimals'=>6],
          // удержание кошелька
          ['name'=>'walletHtml','label'=>'Wallet','escaped' => false,'limit' => 5500],
          ['name'=>'payout_method','label'=>'Method','type'=>'text'],
          ['name'=>'statusHtml','label'=>'Status','escaped' => false,'limit' => 5500],
          ['name'=>'approved_at','label'=>'Approved','type'=>'datetime'],
          ['name'=>'paid_at','label'=>'Paid','type'=>'datetime'],
          // целевая выплата
          ['name'=>'payoutHtml','label'=>'Payout','escaped' => false,'limit' => 5500],
        ]);

        // Фильтры
        $this->crud->addFilter(['type'=>'dropdown','name'=>'status','label'=>'Status'], [
            'pending'=>'pending','approved'=>'approved','rejected'=>'rejected','paid'=>'paid'
        ], function ($val){ CRUD::addClause('where','status',$val); });

        // // Кнопки операций
        $this->crud->addButtonFromView('line', 'reject', 'withdraw_reject', 'beginning');
        $this->crud->addButtonFromView('line', 'paid', 'withdraw_paid', 'beginning');
        $this->crud->addButtonFromView('line', 'approve', 'withdraw_approve', 'beginning');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        CRUD::addColumn(['name'=>'payout_details','label'=>'Payout details','type'=>'textarea']);
        CRUD::addColumn(['name'=>'fx_rate','label'=>'FX rate','type'=>'number','decimals'=>6]);
        CRUD::addColumn(['name'=>'fx_from','label'=>'FX from','type'=>'text']);
        CRUD::addColumn(['name'=>'fx_to','label'=>'FX to','type'=>'text']);
        CRUD::addColumn(['name'=>'approved_by','label'=>'Approved by','type'=>'number']);
        CRUD::addColumn(['name'=>'paid_by','label'=>'Paid by','type'=>'number']);
    }

    // Кастомные POST-действия
    public function approve(Request $r)
    {
        $id = (int)$r->route('id');
        app(\Backpack\Profile\app\Services\WithdrawalService::class)
            ->approve($id, backpack_user()->id, $r->input('fx_rate'), $r->input('fx_from'), $r->input('fx_to'));
        \Alert::success('Approved')->flash();
        return back();
    }

    public function reject(Request $r)
    {
        $id = (int)$r->route('id');
        app(\Backpack\Profile\app\Services\WithdrawalService::class)
            ->reject($id, backpack_user()->id, 'admin_reject');
        \Alert::success('Rejected')->flash();
        return back();
    }

    public function paid(Request $r)
    {
        $id = (int)$r->route('id');
        app(\Backpack\Profile\app\Services\WithdrawalService::class)
            ->markPaid($id, backpack_user()->id);
        \Alert::success('Marked as paid')->flash();
        return back();
    }
}
