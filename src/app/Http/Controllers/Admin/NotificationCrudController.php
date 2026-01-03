<?php

namespace Backpack\Profile\app\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Profile\app\Http\Requests\NotificationRequest;
use Backpack\Profile\app\Models\Notification;

class NotificationCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        $this->crud->setModel(Notification::class);
        $this->crud->setRoute(config('backpack.base.route_prefix', 'admin') . '/notifications');
        $this->crud->setEntityNameStrings('notification', 'notifications');
    }

    protected function setupListOperation(): void
    {
        $this->crud->addColumns([
            ['name' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['name' => 'title', 'label' => 'Title', 'type' => 'text_progress'],
            ['name' => 'variant', 'label' => 'Variant', 'type' => 'text'],
            ['name' => 'audience', 'label' => 'Audience', 'type' => 'text'],
            ['name' => 'target_type', 'label' => 'Target', 'type' => 'text'],
            [
                'name' => 'user_id',
                'label' => 'User',
                'type' => 'user_card',
                'user_model' => config('backpack.profile.user_model', \App\Models\User::class),
            ],
            ['name' => 'is_pinned', 'label' => 'Pinned', 'type' => 'boolean'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
            ['name' => 'is_archived', 'label' => 'Archived', 'type' => 'boolean'],
            ['name' => 'published_at', 'label' => 'Published at', 'type' => 'datetime'],
            ['name' => 'expires_at', 'label' => 'Expires at', 'type' => 'datetime'],
            ['name' => 'created_at', 'label' => 'Created', 'type' => 'datetime'],
        ]);

        $this->crud->addFilter([
            'name' => 'audience',
            'label' => 'Audience',
            'type' => 'dropdown',
        ], array_combine(Notification::audiences(), Notification::audiences()), function ($value) {
            $this->crud->addClause('where', 'audience', $value);
        });

        $this->crud->addFilter([
            'name' => 'variant',
            'label' => 'Variant',
            'type' => 'dropdown',
        ], array_combine(Notification::variants(), Notification::variants()), function ($value) {
            $this->crud->addClause('where', 'variant', $value);
        });

        $this->crud->addFilter([
            'name' => 'target_type',
            'label' => 'Target',
            'type' => 'dropdown',
        ], array_combine(Notification::targetTypes(), Notification::targetTypes()), function ($value) {
            $this->crud->addClause('where', 'target_type', $value);
        });

        $this->crud->addFilter([
            'name' => 'is_pinned',
            'label' => 'Pinned?',
            'type' => 'dropdown',
        ], [1 => 'Yes', 0 => 'No'], function ($value) {
            $this->crud->addClause('where', 'is_pinned', (bool) $value);
        });

        $this->crud->addFilter([
            'name' => 'is_archived',
            'label' => 'Archived?',
            'type' => 'dropdown',
        ], [1 => 'Yes', 0 => 'No'], function ($value) {
            $this->crud->addClause('where', 'is_archived', (bool) $value);
        });
    }

    protected function setupCreateOperation(): void
    {
        $this->crud->setValidation(NotificationRequest::class);

        $this->crud->addFields([
            [
                'name' => 'notification_event_id',
                'label' => 'Event template',
                'type' => 'select',
                'entity' => 'event',
                'attribute' => 'key',
                'model' => \Backpack\Profile\app\Models\NotificationEvent::class,
                'placeholder' => 'Manual notification',
                'allows_null' => true,
            ],
            [
                'name' => 'kind',
                'label' => 'Kind',
                'type' => 'select_from_array',
                'options' => array_combine(Notification::kinds(), Notification::kinds()),
                'default' => Notification::KIND_MANUAL,
            ],
            [
                'name' => 'target_type',
                'label' => 'Target',
                'type' => 'select_from_array',
                'options' => array_combine(Notification::targetTypes(), Notification::targetTypes()),
                'default' => Notification::TARGET_BROADCAST,
            ],
            [
                'name' => 'audience',
                'label' => 'Audience',
                'type' => 'select_from_array',
                'options' => array_combine(Notification::audiences(), Notification::audiences()),
                'default' => Notification::AUDIENCE_ALL,
                'hint' => 'Who will see this notification (for broadcast type).',
            ],
            [
                'name' => 'user_id',
                'label' => 'User (for personal notifications)',
                'type' => 'select2',
                'entity' => 'user',
                'model' => config('backpack.profile.user_model', \App\Models\User::class),
                'attribute' => 'email',
            ],
            [
                'name' => 'variant',
                'label' => 'Variant',
                'type' => 'select_from_array',
                'options' => array_combine(Notification::variants(), Notification::variants()),
                'default' => Notification::VARIANT_INFO,
            ],
            [
                'name' => 'icon',
                'label' => 'Icon (emoji)',
                'type' => 'emoji_picker',
                'hint' => 'Выберите эмодзи для отображения в списке уведомлений.',
            ],
            [
                'name' => 'is_pinned',
                'label' => 'Pinned',
                'type' => 'checkbox',
            ],
            [
                'name' => 'is_active',
                'label' => 'Active',
                'type' => 'checkbox',
                'default' => 1,
            ],
            [
                'name' => 'is_archived',
                'label' => 'Archived',
                'type' => 'checkbox',
            ],
            [
                'name' => 'title',
                'label' => 'Title',
                'type' => 'text',
                'translatable' => true,
            ],
            [
                'name' => 'excerpt',
                'label' => 'Short text',
                'type' => 'textarea',
                'translatable' => true,
            ],
            [
                'name' => 'body',
                'label' => 'Full text',
                'type' => 'ckeditor',
                'translatable' => true,
            ],
            [
                'name' => 'meta',
                'label' => 'Meta (CTA, links, misc)',
                'type' => 'table',
                'columns' => [
                    'action_url' => 'Action URL',
                    'action_label' => 'Action label',
                ],
            ],
            [
                'name' => 'published_at',
                'label' => 'Publish at',
                'type' => 'datetime_picker',
            ],
            [
                'name' => 'expires_at',
                'label' => 'Expires at',
                'type' => 'datetime_picker',
            ],
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $this->setupCreateOperation();
    }
}
