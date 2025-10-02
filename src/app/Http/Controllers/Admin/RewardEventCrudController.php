<?php
// src/app/Http/Controllers/Admin/RewardEventCrudController.php
namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Illuminate\Http\Request;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class RewardEventCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        $this->crud->setModel(\Backpack\Profile\app\Models\RewardEvent::class);
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin').'/reward-events');
        $this->crud->setEntityNameStrings('reward event','reward events');

        // read-only
        $this->crud->denyAccess(['create','update','delete']);
    }

    protected function setupListOperation()
    {
        $this->crud->addColumns([
            ['name'=>'id','type'=>'number','label'=>'ID'],
            ['name'=>'created_at','type'=>'datetime','label'=>'Created'],
            [
                'name'       => 'actor_user_id',
                'label'      => 'Actor User',
                'type'       => 'user_card',
                'user_model' => config('backpack.profile.user_model', \App\Models\User::class),
            ],
            [
                'name'  => 'trigger',
                'label' => 'Триггер',
                'type'  => 'view',
                'view'  => 'crud::columns.trigger', // см. файл ниже
            ],
            [
                'name'  => 'external_id',
                'label' => 'External ID',
                'type'  => 'view',
                'view'  => 'crud::columns.external_id', // см. файл ниже
            ],
            ['name'=>'is_reversal','type'=>'boolean','label'=>'Reversal'],
            ['name'=>'statusHtml','label'=>'Status', 'escaped' => false,'limit' => 5500],
            ['name'=>'attempts','type'=>'number','label'=>'Attempts'],
            ['name'=>'processed_at','type'=>'datetime','label'=>'Processed at'],
            ['name'=>'happened_at','type'=>'datetime','label'=>'Happened at'],
        ]);


        // Фильтры
        $this->crud->addFilter(['type'=>'dropdown','name'=>'status','label'=>'Status'], [
            'pending'=>'pending','processing'=>'processing','processed'=>'processed','failed'=>'failed'
        ], function ($val){ $this->crud->addClause('where','status',$val); });

        $this->crud->addFilter(['type'=>'dropdown','name'=>'is_reversal','label'=>'Reversal?'], [
            1=>'Yes',0=>'No'
        ], function ($val){ $this->crud->addClause('where','is_reversal',(int)$val); });

        $this->crud->addFilter(['type'=>'text','name'=>'trigger','label'=>'Trigger'], false,
            function ($val){ $this->crud->addClause('where','trigger','like',"%$val%"); });

        $this->crud->addFilter(['type'=>'date_range','name'=>'created_at','label'=>'Created at'], false,
            function ($val){
                $dates = json_decode($val, true);
                $this->crud->addClause('whereDate','created_at','>=',$dates['from']);
                $this->crud->addClause('whereDate','created_at','<=',$dates['to']);
            });

        // Кнопки действий
        $this->crud->addButtonFromView('line', 'process_event', 'reward_event_process', 'beginning');
        $this->crud->addButtonFromView('line', 'reverse_event', 'reward_event_reverse', 'beginning');
    }

    protected function setupShowOperation()
    {
        $this->setupListOperation();
        $this->crud->addColumns([
            ['name'=>'subject_type','type'=>'text','label'=>'Subject type'],
            ['name'=>'subject_id','type'=>'text','label'=>'Subject id'],
            ['name'=>'parent_event_id','type'=>'number','label'=>'Parent Event'],
            ['name'=>'last_error','type'=>'textarea','label'=>'Last error'],
            ['name'=>'payload','type'=>'textarea','label'=>'Payload (JSON)'],
        ]);
    }

    // POST /admin/reward-events/{id}/process
    public function process(Request $r, int $id)
    {
        app(\Backpack\Profile\app\Services\ReferralService::class)->process($id);
        \Alert::success('Event processed (queued logic executed).')->flash();
        return back();
    }

    // POST /admin/reward-events/{id}/reverse
    public function reverse(Request $r, int $id)
    {
        $res = app(\Backpack\Profile\app\Services\ReferralService::class)->reverseEvent($id, 'admin_reverse');
        if ($res) {
            \Alert::success('Reversal created.')->flash();
        } else {
            \Alert::warning('Reversal not created (already reversed or invalid).')->flash();
        }
        return back();
    }
}
