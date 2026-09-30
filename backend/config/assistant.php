<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Implementation of App\Contracts\AiAssistant: "ollama" talks to the
    | Ollama service, "fake" is the programmable fake used by the tests.
    |
    */

    'driver' => env('ASSISTANT_DRIVER', 'ollama'),

    /*
    |--------------------------------------------------------------------------
    | Ollama
    |--------------------------------------------------------------------------
    |
    | base_url: Ollama inside the Docker network (the port is not published).
    | model: the shop model created from docker/ollama/Modelfile.
    | think: sent to /api/chat; false disables the reasoning phase of the
    | models that support it, null leaves the field out of the request.
    |
    */

    'base_url' => env('ASSISTANT_BASE_URL', 'http://ollama:11434'),

    'model' => env('ASSISTANT_MODEL', 'casa-del-silicio'),

    'think' => false,

    /*
    |--------------------------------------------------------------------------
    | Timeouts, in seconds
    |--------------------------------------------------------------------------
    |
    | connect_timeout: Ollama stopped or unreachable.
    | timeout: the whole answer, every /api/chat round included (tool calls).
    | lock_margin: added to timeout for the lifetime of a concurrency slot, so
    | a slot held by a dead worker frees itself.
    |
    */

    'connect_timeout' => 3,

    'timeout' => 120,

    'lock_margin' => 30,

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'max_messages' => 10,

    'max_message_length' => 500,

    'max_assistant_message_length' => 4000,

    'max_tool_results' => 8,

    'max_tool_rounds' => 3,

    'max_products' => 4,

    'max_concurrent' => 1,

];
