<?php

it('reports health with the deployed commit', function () {
    config(['app.version' => 'abc1234']);

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'commit' => 'abc1234',
            'database' => 'ok',
        ]);
});

it('serves the OpenAPI document', function () {
    $this->getJson('/api/docs.json')
        ->assertOk()
        ->assertJsonPath('openapi', fn ($v) => str_starts_with($v, '3.'));
});

it('rejects unauthenticated access to protected routes', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
