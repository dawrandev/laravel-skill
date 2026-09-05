<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture tests
|--------------------------------------------------------------------------
|
| These turn the project's conventions into failing tests. Copy this file to
| tests/Feature/ArchTest.php and adjust it to what this project actually does.
|
| Delete any rule the project does not follow. A permanently red architecture
| test teaches the team to ignore the suite. If an expectation below does not
| exist in the installed Pest version, remove it rather than substituting an
| API you have not confirmed.
|
*/

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| Presets
|--------------------------------------------------------------------------
*/

// Blocks debug and deprecated functions: die, var_dump, and similar.
arch()->preset()->php();

// Blocks risky functions: eval, md5, extract, and similar.
arch()->preset()->security();

// Laravel conventions: controller suffixes, base classes, controllers free of
// business logic. Drop this line if the project has controllers with actions
// outside the seven resource methods and you do not want to list exceptions.
arch()->preset()->laravel();

/*
|--------------------------------------------------------------------------
| Global
|--------------------------------------------------------------------------
*/

arch('the application uses strict types and leaves no debug calls behind')
    ->expect('App')
    ->toUseStrictTypes()
    ->not->toUse(['dd', 'dump', 'die', 'var_dump', 'print_r', 'ray', 'exit']);

arch('environment variables are read only in configuration files')
    ->expect('env')
    ->not->toBeUsed();

/*
|--------------------------------------------------------------------------
| HTTP layer
|--------------------------------------------------------------------------
*/

arch('controllers are suffixed')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('form requests extend the framework base class')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class)
    ->toHaveSuffix('Request');

arch('resources extend the framework base class')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class)
    ->toHaveSuffix('Resource');

// Enforces the layering rule: business operations live in actions and services,
// not in controllers. Remove this if the project deliberately allows simple
// controllers to touch Eloquent directly.
arch('controllers do not query models directly')
    ->expect('App\Models')
    ->not->toBeUsedIn('App\Http\Controllers');

/*
|--------------------------------------------------------------------------
| Business logic
|--------------------------------------------------------------------------
*/

arch('actions are final and expose a single entry point')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('actions do not reach for the current request')
    ->expect('App\Actions')
    ->not->toUse(['request', 'Illuminate\Support\Facades\Request']);

arch('services are final')
    ->expect('App\Services')
    ->classes()
    ->toBeFinal();

arch('data objects carry no behaviour beyond construction and conversion')
    ->expect('App\Data')
    ->classes()
    ->toBeFinal();

/*
|--------------------------------------------------------------------------
| Domain
|--------------------------------------------------------------------------
*/

arch('models extend the framework base class')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueue::class);

arch('policies are suffixed')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

/*
|--------------------------------------------------------------------------
| Project-specific
|--------------------------------------------------------------------------
|
| Add rules here as the project makes decisions. Examples:
|
| arch('the shop domain stays independent of billing')
|     ->expect('App\Domain\Shop')
|     ->not->toUse('App\Domain\Billing');
|
| arch('nothing outside the provider resolves out of the container')
|     ->expect(['app', 'resolve'])
|     ->not->toBeUsed()
|     ->ignoring('App\Providers');
|
*/
