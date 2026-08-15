<?php

use App\Providers\AppServiceProvider;
use App\Providers\TypeScriptTransformerServiceProvider;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;

return array_values(array_filter([
    AppServiceProvider::class,

    // Generates TypeScript types from PHP and is only ever run by hand during
    // development. Its package is a dev dependency, so production's --no-dev
    // install has no parent class for the provider to extend; registering it
    // there is a fatal error rather than a missing feature.
    class_exists(TypeScriptTransformerApplicationServiceProvider::class)
        ? TypeScriptTransformerServiceProvider::class
        : null,
]));
