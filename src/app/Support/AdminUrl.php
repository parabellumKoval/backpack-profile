<?php

namespace Backpack\Profile\app\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminUrl
{
    public static function forModel(?string $modelClass, ?int $id): ?string
    {
        if (!$modelClass || !$id) {
            return null;
        }

        // Если в модели есть статический метод adminRoute($id) — используем его.
        if (method_exists($modelClass, 'adminRoute')) {
            try {
                return (string) call_user_func([$modelClass, 'adminRoute'], $id);
            } catch (\Throwable) {}
        }

        // Пытаемся угадать route по соглашениям Backpack
        $base = Str::kebab(class_basename($modelClass)); // e.g. Review -> reviews
        $candidates = [
            'crud.'.Str::snake(class_basename($modelClass)).'.show', // crud.review.show
            'crud.'.$base.'.show',                                    // crud.reviews.show
        ];

        foreach ($candidates as $name) {
            if (Route::has($name)) {
                return route($name, $id);
            }
        }

        // Фолбэк на /{prefix}/{entity}/{id}/show
        $prefix = config('backpack.base.route_prefix', 'admin');

        return url($prefix.'/'.$base.'/'.$id.'/show');
    }
}
