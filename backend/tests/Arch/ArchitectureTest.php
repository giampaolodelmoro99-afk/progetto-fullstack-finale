<?php

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('no debugging helpers in application code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('api controllers are final and suffixed')
    ->expect('App\Http\Controllers\Api')
    ->toBeFinal()
    ->toHaveSuffix('Controller');

arch('api controllers do not query the database directly')
    ->expect('App\Http\Controllers\Api')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('actions are final, suffixed and expose only execute')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveSuffix('Action')
    ->toHaveMethod('execute')
    ->not->toHavePublicMethodsBesides(['execute', '__construct']);

arch('form requests extend the base form request')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class)
    ->toHaveSuffix('Request');

arch('resources extend json resource')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class)
    ->toHaveSuffix('Resource');

arch('enums live in the enums namespace')
    ->expect('App\Enums')
    ->toBeEnums();

arch('contracts are interfaces')
    ->expect('App\Contracts')
    ->toBeInterfaces();

arch('middleware are final')
    ->expect('App\Http\Middleware')
    ->toBeFinal();

arch('console commands are final and suffixed')
    ->expect('App\Console\Commands')
    ->toBeFinal()
    ->toHaveSuffix('Command');
