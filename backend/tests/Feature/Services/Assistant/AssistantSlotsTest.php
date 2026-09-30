<?php

use App\Services\Assistant\AssistantSlots;
use Illuminate\Support\Facades\Cache;

it('takes the only slot and refuses a second one', function () {
    $first = app(AssistantSlots::class)->acquire();

    expect($first)->not->toBeNull()
        ->and(app(AssistantSlots::class)->acquire())->toBeNull();
});

it('frees the slot when released', function () {
    app(AssistantSlots::class)->acquire()->release();

    expect(Cache::lock('assistant:slot:1')->get())->toBeTrue();
});

it('hands out as many slots as max_concurrent', function () {
    config(['assistant.max_concurrent' => 2]);

    $first = app(AssistantSlots::class)->acquire();
    $second = app(AssistantSlots::class)->acquire();

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and(app(AssistantSlots::class)->acquire())->toBeNull();

    $first->release();

    expect(app(AssistantSlots::class)->acquire())->not->toBeNull();
});

it('does not use a slot already held elsewhere', function () {
    Cache::lock('assistant:slot:1', 60)->get();

    expect(app(AssistantSlots::class)->acquire())->toBeNull();
});

it('takes and releases the slot with the database cache of the application', function () {
    config(['cache.default' => 'database']);

    $slot = app(AssistantSlots::class)->acquire();

    expect($slot)->not->toBeNull()
        ->and(app(AssistantSlots::class)->acquire())->toBeNull();

    $slot->release();

    expect(app(AssistantSlots::class)->acquire())->not->toBeNull();
});
