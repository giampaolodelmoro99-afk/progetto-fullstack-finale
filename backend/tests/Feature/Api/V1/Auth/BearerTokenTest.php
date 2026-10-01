<?php

it('ignores bearer tokens and answers 401 on a protected route', function () {
    $this->withHeader('Authorization', 'Bearer 1|abcdefghijklmnopqrstuvwxyz')
        ->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);
});
