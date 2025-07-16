<?php

namespace TopoclimbCH\Core\Routing;

use Attribute;

/**
 * Middleware attribute for routes
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Middleware
{
    public function __construct(
        public string|array $middleware
    ) {
        // Normalize to array
        if (is_string($this->middleware)) {
            $this->middleware = [$this->middleware];
        }
    }
}