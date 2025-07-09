<?php

namespace TopoclimbCH\Core\Routing;

use Attribute;

/**
 * Route attribute for PHP 8+ routing
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public string|array $methods = ['GET'],
        public string $name = '',
        public array $middlewares = [],
        public array $requirements = [],
        public array $defaults = []
    ) {
        // Normalize methods to array
        if (is_string($this->methods)) {
            $this->methods = [$this->methods];
        }
        
        // Normalize methods to uppercase
        $this->methods = array_map('strtoupper', $this->methods);
    }
}