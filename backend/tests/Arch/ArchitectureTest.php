<?php

use Illuminate\Contracts\Debug\ShouldntReport;
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

arch('domain exceptions stay out of the error log')
    ->expect('App\Exceptions')
    ->toImplement(ShouldntReport::class)
    ->ignoring([
        'App\Exceptions\Orders\RefundFailedException',
        'App\Exceptions\Auth\PasskeyCeremonyFailedException',
        'App\Exceptions\Assistant\AssistantTimeoutException',
        'App\Exceptions\Assistant\AssistantFailedException',
    ]);

arch('failures of external services and ceremonies are reported')
    ->expect([
        'App\Exceptions\Orders\RefundFailedException',
        'App\Exceptions\Auth\PasskeyCeremonyFailedException',
        'App\Exceptions\Assistant\AssistantTimeoutException',
        'App\Exceptions\Assistant\AssistantFailedException',
    ])
    ->not->toImplement(ShouldntReport::class);

arch('the assistant services implement the contract')
    ->expect(['App\Services\Assistant\OllamaAssistant', 'App\Services\Assistant\FakeAiAssistant'])
    ->toImplement('App\Contracts\AiAssistant');

arch('the second factor verifiers implement the contract')
    ->expect(['App\Services\Security\TotpVerifier', 'App\Services\Security\RecoveryCodeVerifier', 'App\Services\Security\PasskeyVerifier'])
    ->toImplement('App\Contracts\Security\SecondFactorVerifier');

arch('the passkey ceremonies implement the contract')
    ->expect(['App\Services\Security\WebAuthnPasskeyCeremony', 'Tests\Support\FakePasskeyCeremony'])
    ->toImplement('App\Contracts\Security\PasskeyCeremony');

arch('notifications are final and queued')
    ->expect('App\Notifications')
    ->toBeFinal()
    ->toHaveSuffix('Notification')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('notifications with a link that performs an action are encrypted in the queue')
    ->expect([
        'App\Notifications\ResetPasswordNotification',
        'App\Notifications\EmailChangeConfirmationNotification',
        'App\Notifications\EmailChangeRequestedNotification',
        'App\Notifications\VerifyEmailNotification',
    ])
    ->toImplement('Illuminate\Contracts\Queue\ShouldBeEncrypted');

arch('validation rules are final')
    ->expect('App\Rules')
    ->toBeFinal()
    ->toImplement('Illuminate\Contracts\Validation\ValidationRule');
