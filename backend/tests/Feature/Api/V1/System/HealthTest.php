<?php

it('reports that the service is up', function () {
    $this->getJson(route('v1.health'))
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('is reachable at the versioned api path', function () {
    $this->getJson('/api/v1/health')->assertOk();
});

it('allows credentialed cross-origin requests from the frontend', function () {
    $this->withHeaders(['Origin' => frontendOrigin()])
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', frontendOrigin())
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('never allows other origins', function () {
    $response = $this->withHeaders(['Origin' => 'http://evil.test'])
        ->getJson('/api/v1/health');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('http://evil.test')
        ->not->toBe('*');
});

it('answers with json even without an accept header', function () {
    $this->get('/api/v1/health', ['Accept' => 'text/html'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});

it('answers unknown api routes with a json 404', function () {
    $this->get('/api/v1/does-not-exist', ['Accept' => 'text/html'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message']);
});
