<?php

use Inertia\Pagination\Paginator;

test('should return the URL for a given page number', function () {
    $paginator = new Paginator(
        items: collect(range(1, 100)),
        total: 100,
        perPage: 10,
        currentPage: 1,
        options: ['path' => 'http://example.com']
    );

    expect($paginator->url(page: 1))->toBe('http://example.com');
    expect($paginator->url(page: 2))->toBe('http://example.com?page=2');
    expect($paginator->url(page: 3))->toBe('http://example.com?page=3');
});
