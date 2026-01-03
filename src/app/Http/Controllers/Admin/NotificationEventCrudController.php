<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Profile\app\Http\Requests\NotificationEventRequest;
use Backpack\Profile\app\Models\NotificationEvent;

class NotificationEventCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        $this->crud->setModel(NotificationEvent::class);
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin') . '/notification-events');
        $this->crud->setEntityNameStrings('notification event', 'notification events');
    }

    protected function setupListOperation(): void
    {
        $this->crud->addColumns([
            ['name' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['name' => 'key', 'label' => 'Key', 'type' => 'text'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text_progress'],
            ['name' => 'variant', 'label' => 'Variant', 'type' => 'text'],
            ['name' => 'audience', 'label' => 'Audience', 'type' => 'text'],
            ['name' => 'target_type', 'label' => 'Target', 'type' => 'text'],
            ['name' => 'is_pinned', 'label' => 'Pinned', 'type' => 'boolean'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
            ['name' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
        ]);

        $this->crud->addFilter([
            'name' => 'variant',
            'label' => 'Variant',
            'type' => 'dropdown',
        ], array_combine(NotificationEvent::variants(), NotificationEvent::variants()), function ($value) {
            $this->crud->addClause('where', 'variant', $value);
        });

        $this->crud->addFilter([
            'name' => 'audience',
            'label' => 'Audience',
            'type' => 'dropdown',
        ], array_combine(NotificationEvent::audiences(), NotificationEvent::audiences()), function ($value) {
            $this->crud->addClause('where', 'audience', $value);
        });
    }

    protected function setupCreateOperation(): void
    {
        $this->crud->setValidation(NotificationEventRequest::class);

        $this->crud->addFields([
            [
                'name' => 'key',
                'label' => 'Key',
                'type' => 'text',
            ],
            [
                'name' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'translatable' => true,
            ],
            [
                'name' => 'variant',
                'label' => 'Variant',
                'type' => 'select_from_array',
                'options' => array_combine(NotificationEvent::variants(), NotificationEvent::variants()),
                'default' => NotificationEvent::VARIANT_INFO,
            ],
            [
                'name' => 'icon',
                'label' => 'Default icon (emoji)',
                'type' => 'emoji_picker',
                'hint' => 'Эмодзи по умолчанию для уведомлений этого события.',
            ],
            [
                'name' => 'audience',
                'label' => 'Audience',
                'type' => 'select_from_array',
                'options' => array_combine(NotificationEvent::audiences(), NotificationEvent::audiences()),
                'default' => NotificationEvent::AUDIENCE_AUTHENTICATED,
            ],
            [
                'name' => 'target_type',
                'label' => 'Target',
                'type' => 'select_from_array',
                'options' => array_combine(NotificationEvent::targetTypes(), NotificationEvent::targetTypes()),
                'default' => NotificationEvent::TARGET_PERSONAL,
            ],
            [
                'name' => 'is_pinned',
                'label' => 'Pinned by default',
                'type' => 'checkbox',
            ],
            [
                'name' => 'is_active',
                'label' => 'Active',
                'type' => 'checkbox',
                'default' => 1,
            ],
            [
                'name' => 'title',
                'label' => 'Default title',
                'type' => 'text',
                'translatable' => true,
            ],
            [
                'name' => 'excerpt',
                'label' => 'Default short text',
                'type' => 'textarea',
                'translatable' => true,
            ],
            [
                'name' => 'body',
                'label' => 'Default full text',
                'type' => 'ckeditor',
                'translatable' => true,
            ],
            [
                'name' => 'meta',
                'label' => 'Meta',
                'type' => 'table',
                'columns' => [
                    'action_url' => 'Action URL',
                    'action_label' => 'Action label',
                ],
            ],
            [
                'name' => 'options',
                'label' => 'Options',
                'type' => 'table',
                'columns' => [
                    'placeholder' => 'Placeholder',
                    'value' => 'Default',
                ],
            ],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
