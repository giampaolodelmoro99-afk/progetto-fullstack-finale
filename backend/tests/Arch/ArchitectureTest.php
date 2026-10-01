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

arch('enums live in the enums namespace and expose an italian label')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toHaveMethod('label');

arch('observers are final and suffixed')
    ->expect('App\Observers')
    ->toBeFinal()
    ->toHaveSuffix('Observer');

arch('models extend eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('support classes are final')
    ->expect('App\Support')
    ->toBeFinal();

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

arch('services are final')
    ->expect('App\Services')
    ->toBeFinal();

arch('exceptions are final and suffixed')
    ->expect('App\Exceptions')
    ->toBeFinal()
    ->toHaveSuffix('Exception');

arch('the assistant services implement the contract')
    ->expect(['App\Services\Assistant\OllamaAssistant', 'App\Services\Assistant\FakeAiAssistant'])
    ->toImplement('App\Contracts\AiAssistant');

arch('notifications are final and queued')
    ->expect('App\Notifications')
    ->toBeFinal()
    ->toHaveSuffix('Notification')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('validation rules are final')
    ->expect('App\Rules')
    ->toBeFinal()
    ->toImplement('Illuminate\Contracts\Validation\ValidationRule');
