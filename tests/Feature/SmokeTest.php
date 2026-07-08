<?php

declare(strict_types=1);

test('the welcome page renders', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('MediaForge');
});

test('the health endpoint responds', function () {
    $this->get('/up')->assertOk();
});
