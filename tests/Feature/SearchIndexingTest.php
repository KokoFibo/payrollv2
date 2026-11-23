<?php

test('public pages block search engine and ai indexing', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertHeader(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai',
        )
        ->assertSee('noai', false)
        ->assertSee('GPTBot', false)
        ->assertSee('ClaudeBot', false);
});

test('robots file blocks search and ai crawlers', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain("User-agent: *\nDisallow: /")
        ->toContain("User-agent: Google-Extended\nDisallow: /")
        ->toContain("User-agent: GPTBot\nDisallow: /")
        ->toContain("User-agent: ClaudeBot\nDisallow: /")
        ->toContain("User-agent: CCBot\nDisallow: /");
});
