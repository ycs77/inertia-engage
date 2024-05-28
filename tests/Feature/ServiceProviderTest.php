<?php

test('should get the overrided paginator class', function () {
    $paginator = app()->make(\Illuminate\Pagination\LengthAwarePaginator::class, [
        'items' => [],
        'total' => 0,
        'perPage' => 10,
    ]);

    expect($paginator)->toBeInstanceOf(\Inertia\Pagination\Paginator::class);
});

test('should called exception handler from inertia response factory', function () {
    expect(\Inertia\ResponseFactory::hasMacro('exception'))->toBeTrue();
});
