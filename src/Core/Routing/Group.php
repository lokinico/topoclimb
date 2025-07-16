<?php

namespace TopoclimbCH\Core\Routing;

use Attribute;

/**
 * Route group attribute for controllers
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Group
{
    public function __construct(
        public string $prefix = '',
        public array $middlewares = [],
        public string $name = ''
    ) {}
}