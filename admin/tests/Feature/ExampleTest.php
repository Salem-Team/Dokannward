<?php

test('the root redirects to the admin panel', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin');
});

test('the admin login page is reachable', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
});
