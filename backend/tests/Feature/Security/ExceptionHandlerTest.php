<?php

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;

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

it('answers 405 in italian for a wrong method', function () {
    $this->deleteJson('/api/v1/health')
        ->assertMethodNotAllowed()
        ->assertExactJson(['message' => 'Metodo non consentito per questo indirizzo.']);
});
