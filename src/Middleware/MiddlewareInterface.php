<?php
// src/Middleware/MiddlewareInterface.php

namespace TopoclimbCH\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface MiddlewareInterface
{
    /**
     * Handle the request
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
