<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new \App\App(__DIR__);

$app->routingFactory->register(['finding' => \App\Routing\Finding::class]);

$app->map->middleware(new \App\Middlewares\TestMiddleware());

$app->map['test']->any('/:foo')->group(function(\Exedra\Routing\Group $group) {
    $group->middleware(new \App\Middlewares\TestMiddleware());

    $group->any('/:bar')->execute(function(\Exedra\Runtime\Context $context) {
        return $context->param('foo') . ' ' . $context->param('bar');
    });
});

return $app;