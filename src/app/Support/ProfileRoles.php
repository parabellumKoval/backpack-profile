<?php

namespace Backpack\Profile\app\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ProfileRoles
{
    public static function definitions(): array
    {
        $roles = config('backpack.profile.roles', config('profile.roles', []));
        $roles = is_array($roles) ? $roles : [];

        return collect($roles)->mapWithKeys(function ($role, $key) {
            $definition = is_array($role) ? $role : ['label' => $role];

            $definition['label'] = $definition['label'] ?? Str::headline((string) $key);
            $definition['badge_class'] = $definition['badge_class'] ?? 'badge-secondary';
            $definition['color'] = $definition['color'] ?? '#6c757d';
            $definition['text_color'] = $definition['text_color'] ?? '#ffffff';

            return [$key => $definition];
        })->toArray();
    }

    public static function defaultRole(): ?string
    {
        return config('backpack.profile.default_role', config('profile.default_role'));
    }

    public static function options(): array
    {
        return collect(static::definitions())
            ->mapWithKeys(fn ($role, $key) => [$key => $role['label'] ?? Str::headline((string) $key)])
            ->toArray();
    }

    public static function definition(?string $role): ?array
    {
        if (!$role) {
            return null;
        }

        return Arr::get(static::definitions(), $role);
    }

    public static function badgeMeta(?string $role): array
    {
        $definition = static::definition($role) ?? [];
        $label = $definition['label'] ?? ($role ? Str::headline($role) : '—');

        return [
            'label' => $label,
            'class' => $definition['badge_class'] ?? 'badge-secondary',
            'color' => $definition['color'] ?? '#6c757d',
            'text_color' => $definition['text_color'] ?? '#ffffff',
        ];
    }

    public static function roleFields(): array
    {
        $fields = config('backpack.profile.role_fields', config('profile.role_fields', []));

        return is_array($fields) ? $fields : [];
    }

    public static function fieldsForRole(?string $role): array
    {
        if (!$role) {
            return [];
        }

        $fields = Arr::get(static::roleFields(), $role, []);

        return is_array($fields) ? $fields : [];
    }

    public static function validationRulesForRole(?string $role): array
    {
        $rules = [];

        foreach (static::fieldsForRole($role) as $field) {
            $name = $field['name'] ?? null;
            if (!$name) {
                continue;
            }

            $rule = $field['validation_rules'] ?? $field['rules'] ?? null;
            if ($rule === null) {
                continue;
            }

            $rules[$name] = $rule;
        }

        return $rules;
    }

    public static function attributeLabelsForRole(?string $role): array
    {
        $labels = [];

        foreach (static::fieldsForRole($role) as $field) {
            $name = $field['name'] ?? null;
            if (!$name) {
                continue;
            }

            $label = $field['label'] ?? Str::headline($name);
            $labels["role_fields.{$role}.{$name}"] = $label;
        }

        return $labels;
    }
}
