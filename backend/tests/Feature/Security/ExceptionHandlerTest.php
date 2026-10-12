<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('never flashes passwords and card data', function () {
    $handler = app(ExceptionHandler::class);
    $dontFlash = (fn () => $this->dontFlash)->call($handler);

    expect($dontFlash)->toContain('current_password', 'password', 'password_confirmation', 'payment.number', 'payment.cvc');
});

it('answers 404 in italian for a missing api route', function () {
    $this->getJson('/api/v1/inesistente')
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
});

it('answers 404 in italian for a missing model', function () {
    Route::middleware('api')->get('/api/v1/test/users/{user}', fn (User $user) => $user->id);

    $this->getJson('/api/v1/test/users/999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'La risorsa richiesta non esiste.']);
});

it('translates the default authorization message', function () {
    Route::middleware('api')->get('/api/v1/test/denied', fn () => throw new AuthorizationException);

    $this->getJson('/api/v1/test/denied')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Non hai i permessi per questa operazione.']);
});

it('keeps a custom authorization message', function () {
    Route::middleware('api')->get('/api/v1/test/denied', fn () => throw new AuthorizationException('Puoi annullare solo i tuoi ordini.'));

    $this->getJson('/api/v1/test/denied')
        ->assertForbidden()
        ->assertExactJson(['message' => 'Puoi annullare solo i tuoi ordini.']);
});

it('answers 419 in italian for a csrf token mismatch', function () {
    Route::middleware('api')->post('/api/v1/test/csrf', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

    $this->postJson('/api/v1/test/csrf')
        ->assertStatus(419)
        ->assertExactJson(['message' => 'La sessione è scaduta. Ricarica la pagina e riprova.']);
});

it('summarizes the other validation errors in italian', function (array $payload, string $message) {
    fromFrontend()->postJson('/api/v1/register', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', $message);
})->with([
    'one more error' => [['name' => 'Giulia Ferri', 'password' => 'Password123'], 'Il campo email è obbligatorio. (e un altro errore)'],
    'two more errors' => [[], 'Il campo nome è obbligatorio. (e altri 2 errori)'],
]);

it('translates the generic message of a validation error without field messages', function () {
    Route::middleware('api')->post('/api/v1/test/invalid', fn () => throw ValidationException::withMessages([]));

    $this->postJson('/api/v1/test/invalid')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'I dati inviati non sono validi.');
});

it('answers 405 in italian for a wrong method', function () {
    $this->deleteJson('/api/v1/health')
        ->assertMethodNotAllowed()
        ->assertExactJson(['message' => 'Metodo non consentito per questo indirizzo.']);
});
