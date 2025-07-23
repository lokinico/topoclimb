<?php

return array (
  0 => 
  array (
    'method' => 'GET',
    'path' => '/',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'index',
  ),
  1 => 
  array (
    'method' => 'GET',
    'path' => '/debug-home',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'debugTest',
  ),
  2 => 
  array (
    'method' => 'GET',
    'path' => '/map',
    'controller' => 'TopoclimbCH\\Controllers\\MapController',
    'action' => 'index',
  ),
  3 => 
  array (
    'method' => 'GET',
    'path' => '/api/map/sites',
    'controller' => 'TopoclimbCH\\Controllers\\MapController',
    'action' => 'apiSites',
  ),
  4 => 
  array (
    'method' => 'GET',
    'path' => '/api/map/sites/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\MapController',
    'action' => 'apiSiteDetails',
  ),
  5 => 
  array (
    'method' => 'GET',
    'path' => '/api/map/search',
    'controller' => 'TopoclimbCH\\Controllers\\MapController',
    'action' => 'apiGeoSearch',
  ),
  6 => 
  array (
    'method' => 'GET',
    'path' => '/login',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'loginForm',
  ),
  7 => 
  array (
    'method' => 'POST',
    'path' => '/login',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'login',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  8 => 
  array (
    'method' => 'GET',
    'path' => '/logout',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'logout',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  9 => 
  array (
    'method' => 'GET',
    'path' => '/register',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'registerForm',
  ),
  10 => 
  array (
    'method' => 'POST',
    'path' => '/register',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'register',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  11 => 
  array (
    'method' => 'GET',
    'path' => '/forgot-password',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'forgotPasswordForm',
  ),
  12 => 
  array (
    'method' => 'POST',
    'path' => '/forgot-password',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'forgotPassword',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  13 => 
  array (
    'method' => 'GET',
    'path' => '/reset-password',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'resetPasswordForm',
  ),
  14 => 
  array (
    'method' => 'POST',
    'path' => '/reset-password',
    'controller' => 'TopoclimbCH\\Controllers\\AuthController',
    'action' => 'resetPassword',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  15 => 
  array (
    'method' => 'GET',
    'path' => '/regions',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  16 => 
  array (
    'method' => 'GET',
    'path' => '/regions/create',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  17 => 
  array (
    'method' => 'POST',
    'path' => '/regions',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  18 => 
  array (
    'method' => 'GET',
    'path' => '/regions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'show',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  19 => 
  array (
    'method' => 'GET',
    'path' => '/regions/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'edit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  20 => 
  array (
    'method' => 'PUT',
    'path' => '/regions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  21 => 
  array (
    'method' => 'DELETE',
    'path' => '/regions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'destroy',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  22 => 
  array (
    'method' => 'GET',
    'path' => '/sites',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  23 => 
  array (
    'method' => 'GET',
    'path' => '/sites/create',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'form',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  24 => 
  array (
    'method' => 'POST',
    'path' => '/sites',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  25 => 
  array (
    'method' => 'GET',
    'path' => '/sites/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'show',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  26 => 
  array (
    'method' => 'GET',
    'path' => '/sites/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'form',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  27 => 
  array (
    'method' => 'PUT',
    'path' => '/sites/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  28 => 
  array (
    'method' => 'DELETE',
    'path' => '/sites/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'destroy',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  29 => 
  array (
    'method' => 'GET',
    'path' => '/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  30 => 
  array (
    'method' => 'GET',
    'path' => '/sectors/create',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  31 => 
  array (
    'method' => 'POST',
    'path' => '/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  32 => 
  array (
    'method' => 'GET',
    'path' => '/sectors/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'show',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  33 => 
  array (
    'method' => 'GET',
    'path' => '/sectors/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'edit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  34 => 
  array (
    'method' => 'POST',
    'path' => '/sectors/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  35 => 
  array (
    'method' => 'GET',
    'path' => '/sectors/{id}/delete',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'delete',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  36 => 
  array (
    'method' => 'POST',
    'path' => '/sectors/{id}/delete',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'delete',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  37 => 
  array (
    'method' => 'GET',
    'path' => '/books',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  38 => 
  array (
    'method' => 'GET',
    'path' => '/books/create',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'form',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  39 => 
  array (
    'method' => 'POST',
    'path' => '/books',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  40 => 
  array (
    'method' => 'GET',
    'path' => '/books/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'show',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  41 => 
  array (
    'method' => 'GET',
    'path' => '/books/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'form',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  42 => 
  array (
    'method' => 'PUT',
    'path' => '/books/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  43 => 
  array (
    'method' => 'DELETE',
    'path' => '/books/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'destroy',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  44 => 
  array (
    'method' => 'GET',
    'path' => '/books/{id}/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'sectorSelector',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  45 => 
  array (
    'method' => 'POST',
    'path' => '/books/{id}/add-sector',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'addSector',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  46 => 
  array (
    'method' => 'POST',
    'path' => '/books/{id}/remove-sector',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'removeSector',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  47 => 
  array (
    'method' => 'GET',
    'path' => '/routes',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  48 => 
  array (
    'method' => 'GET',
    'path' => '/routes/create',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  49 => 
  array (
    'method' => 'POST',
    'path' => '/routes',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  50 => 
  array (
    'method' => 'GET',
    'path' => '/routes/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'show',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  51 => 
  array (
    'method' => 'GET',
    'path' => '/routes/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'edit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  52 => 
  array (
    'method' => 'POST',
    'path' => '/routes/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  53 => 
  array (
    'method' => 'GET',
    'path' => '/routes/{id}/delete',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'delete',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  54 => 
  array (
    'method' => 'POST',
    'path' => '/routes/{id}/delete',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'delete',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  55 => 
  array (
    'method' => 'GET',
    'path' => '/routes/{id}/log-ascent',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'logAscent',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  56 => 
  array (
    'method' => 'POST',
    'path' => '/routes/{id}/log-ascent',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'recordAscent',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  57 => 
  array (
    'method' => 'GET',
    'path' => '/routes/{id}/comments',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'comments',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  58 => 
  array (
    'method' => 'POST',
    'path' => '/routes/{id}/comments',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'addComment',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  59 => 
  array (
    'method' => 'POST',
    'path' => '/routes/{id}/favorite',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'toggleFavorite',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  60 => 
  array (
    'method' => 'DELETE',
    'path' => '/routes/{id}/favorite',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'removeFavorite',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  61 => 
  array (
    'method' => 'GET',
    'path' => '/events',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  62 => 
  array (
    'method' => 'GET',
    'path' => '/events/create',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  63 => 
  array (
    'method' => 'POST',
    'path' => '/events',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  64 => 
  array (
    'method' => 'GET',
    'path' => '/events/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'show',
    'middlewares' => 
    array (
    ),
  ),
  65 => 
  array (
    'method' => 'POST',
    'path' => '/events/{id}/register',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'register',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  66 => 
  array (
    'method' => 'POST',
    'path' => '/events/{id}/unregister',
    'controller' => 'TopoclimbCH\\Controllers\\EventController',
    'action' => 'unregister',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  67 => 
  array (
    'method' => 'GET',
    'path' => '/alerts',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  68 => 
  array (
    'method' => 'GET',
    'path' => '/alerts/create',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  69 => 
  array (
    'method' => 'POST',
    'path' => '/alerts',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  70 => 
  array (
    'method' => 'GET',
    'path' => '/alerts/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'show',
    'middlewares' => 
    array (
    ),
  ),
  71 => 
  array (
    'method' => 'GET',
    'path' => '/alerts/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'edit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  72 => 
  array (
    'method' => 'POST',
    'path' => '/alerts/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  73 => 
  array (
    'method' => 'DELETE',
    'path' => '/alerts/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'delete',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  74 => 
  array (
    'method' => 'POST',
    'path' => '/alerts/{id}/confirm',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'confirm',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  75 => 
  array (
    'method' => 'GET',
    'path' => '/api/alerts',
    'controller' => 'TopoclimbCH\\Controllers\\AlertController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  76 => 
  array (
    'method' => 'GET',
    'path' => '/forum',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  77 => 
  array (
    'method' => 'GET',
    'path' => '/forum/category/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'category',
    'middlewares' => 
    array (
    ),
  ),
  78 => 
  array (
    'method' => 'GET',
    'path' => '/forum/topic/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'topic',
    'middlewares' => 
    array (
    ),
  ),
  79 => 
  array (
    'method' => 'GET',
    'path' => '/forum/category/{id}/create',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'createTopic',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  80 => 
  array (
    'method' => 'POST',
    'path' => '/forum/category/{id}/create',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'storeTopic',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  81 => 
  array (
    'method' => 'POST',
    'path' => '/forum/topic/{id}/reply',
    'controller' => 'TopoclimbCH\\Controllers\\ForumController',
    'action' => 'reply',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  82 => 
  array (
    'method' => 'GET',
    'path' => '/api/regions',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  83 => 
  array (
    'method' => 'GET',
    'path' => '/api/regions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'apiShow',
    'middlewares' => 
    array (
    ),
  ),
  84 => 
  array (
    'method' => 'GET',
    'path' => '/api/regions/search',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'search',
    'middlewares' => 
    array (
    ),
  ),
  85 => 
  array (
    'method' => 'GET',
    'path' => '/api/regions/{id}/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'apiSectors',
    'middlewares' => 
    array (
    ),
  ),
  86 => 
  array (
    'method' => 'GET',
    'path' => '/api/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  87 => 
  array (
    'method' => 'GET',
    'path' => '/api/sectors/search',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'apiSearch',
    'middlewares' => 
    array (
    ),
  ),
  88 => 
  array (
    'method' => 'GET',
    'path' => '/api/routes',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  89 => 
  array (
    'method' => 'GET',
    'path' => '/api/routes/search',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'apiSearch',
    'middlewares' => 
    array (
    ),
  ),
  90 => 
  array (
    'method' => 'GET',
    'path' => '/api/books',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  91 => 
  array (
    'method' => 'GET',
    'path' => '/api/books/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'apiShow',
    'middlewares' => 
    array (
    ),
  ),
  92 => 
  array (
    'method' => 'GET',
    'path' => '/regions/{id}/weather',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'weather',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  93 => 
  array (
    'method' => 'GET',
    'path' => '/api/sites',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  94 => 
  array (
    'method' => 'GET',
    'path' => '/api/sites/search',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'apiSearch',
    'middlewares' => 
    array (
    ),
  ),
  95 => 
  array (
    'method' => 'GET',
    'path' => '/api/sites/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'apiShow',
    'middlewares' => 
    array (
    ),
  ),
  96 => 
  array (
    'method' => 'GET',
    'path' => '/api/sectors/{id}/routes',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'getRoutes',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  97 => 
  array (
    'method' => 'GET',
    'path' => '/api/books/search',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'apiSearch',
    'middlewares' => 
    array (
    ),
  ),
  98 => 
  array (
    'method' => 'GET',
    'path' => '/api/books/{id}/sectors',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'apiSectors',
    'middlewares' => 
    array (
    ),
  ),
  99 => 
  array (
    'method' => 'GET',
    'path' => '/profile',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'profile',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  100 => 
  array (
    'method' => 'GET',
    'path' => '/profile/ascents',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'ascents',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  101 => 
  array (
    'method' => 'GET',
    'path' => '/profile/favorites',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'favorites',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  102 => 
  array (
    'method' => 'GET',
    'path' => '/ascents',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'ascents',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  103 => 
  array (
    'method' => 'GET',
    'path' => '/favorites',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'favorites',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  104 => 
  array (
    'method' => 'GET',
    'path' => '/settings',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'settings',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  105 => 
  array (
    'method' => 'POST',
    'path' => '/settings/profile',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'updateProfile',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  106 => 
  array (
    'method' => 'POST',
    'path' => '/settings/password',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'updatePassword',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  107 => 
  array (
    'method' => 'GET',
    'path' => '/pending',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'pending',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  108 => 
  array (
    'method' => 'GET',
    'path' => '/banned',
    'controller' => 'TopoclimbCH\\Controllers\\UserController',
    'action' => 'banned',
  ),
  109 => 
  array (
    'method' => 'GET',
    'path' => '/ascents/create',
    'controller' => 'TopoclimbCH\\Controllers\\UserAscentController',
    'action' => 'create',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  110 => 
  array (
    'method' => 'POST',
    'path' => '/ascents',
    'controller' => 'TopoclimbCH\\Controllers\\UserAscentController',
    'action' => 'store',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  111 => 
  array (
    'method' => 'GET',
    'path' => '/ascents/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\UserAscentController',
    'action' => 'edit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  112 => 
  array (
    'method' => 'POST',
    'path' => '/ascents/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\UserAscentController',
    'action' => 'update',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  113 => 
  array (
    'method' => 'GET',
    'path' => '/ascents/export',
    'controller' => 'TopoclimbCH\\Controllers\\UserAscentController',
    'action' => 'export',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  114 => 
  array (
    'method' => 'GET',
    'path' => '/admin',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'index',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  115 => 
  array (
    'method' => 'GET',
    'path' => '/admin/users',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'users',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  116 => 
  array (
    'method' => 'GET',
    'path' => '/admin/reports',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'reports',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  117 => 
  array (
    'method' => 'GET',
    'path' => '/admin/users/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'userEdit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  118 => 
  array (
    'method' => 'POST',
    'path' => '/admin/users/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'userEdit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  119 => 
  array (
    'method' => 'POST',
    'path' => '/admin/users/{id}/toggle-ban',
    'controller' => 'TopoclimbCH\\Controllers\\AdminController',
    'action' => 'userToggleBan',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  120 => 
  array (
    'method' => 'POST',
    'path' => '/regions/{id}/media',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'uploadMedia',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  121 => 
  array (
    'method' => 'DELETE',
    'path' => '/regions/{id}/media/{mediaId}',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'deleteMedia',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  122 => 
  array (
    'method' => 'GET',
    'path' => '/regions/{id}/export',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'export',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  123 => 
  array (
    'method' => 'GET',
    'path' => '/about',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'about',
  ),
  124 => 
  array (
    'method' => 'GET',
    'path' => '/contact',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'contact',
  ),
  125 => 
  array (
    'method' => 'GET',
    'path' => '/privacy',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'privacy',
  ),
  126 => 
  array (
    'method' => 'GET',
    'path' => '/terms',
    'controller' => 'TopoclimbCH\\Controllers\\HomeController',
    'action' => 'terms',
  ),
  127 => 
  array (
    'method' => 'GET',
    'path' => '/404',
    'controller' => 'TopoclimbCH\\Controllers\\ErrorController',
    'action' => 'notFound',
  ),
  128 => 
  array (
    'method' => 'GET',
    'path' => '/403',
    'controller' => 'TopoclimbCH\\Controllers\\ErrorController',
    'action' => 'forbidden',
  ),
  129 => 
  array (
    'method' => 'GET',
    'path' => '/media',
    'controller' => 'TopoclimbCH\\Controllers\\MediaController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  130 => 
  array (
    'method' => 'GET',
    'path' => '/regions/{id}/media',
    'controller' => 'TopoclimbCH\\Controllers\\MediaController',
    'action' => 'uploadForm',
    'middlewares' => 
    array (
    ),
  ),
  131 => 
  array (
    'method' => 'GET',
    'path' => '/geolocation',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  132 => 
  array (
    'method' => 'GET',
    'path' => '/geolocation/directions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'directions',
    'middlewares' => 
    array (
    ),
  ),
  133 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/nearest-sites',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiNearestSites',
    'middlewares' => 
    array (
    ),
  ),
  134 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/nearest-sectors',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiNearestSectors',
    'middlewares' => 
    array (
    ),
  ),
  135 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/directions/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiDirections',
    'middlewares' => 
    array (
    ),
  ),
  136 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/geocode',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiGeocode',
    'middlewares' => 
    array (
    ),
  ),
  137 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/reverse-geocode',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiReverseGeocode',
    'middlewares' => 
    array (
    ),
  ),
  138 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/convert-swiss',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiConvertToSwiss',
    'middlewares' => 
    array (
    ),
  ),
  139 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/weather',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiWeatherByLocation',
    'middlewares' => 
    array (
    ),
  ),
  140 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/nearby-pois',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiNearbyPOIs',
    'middlewares' => 
    array (
    ),
  ),
  141 => 
  array (
    'method' => 'GET',
    'path' => '/api/weather/current',
    'controller' => 'TopoclimbCH\\Controllers\\WeatherController',
    'action' => 'apiCurrent',
    'middlewares' => 
    array (
    ),
  ),
  142 => 
  array (
    'method' => 'GET',
    'path' => '/api/geolocation/search',
    'controller' => 'TopoclimbCH\\Controllers\\GeolocationController',
    'action' => 'apiSearch',
    'middlewares' => 
    array (
    ),
  ),
  143 => 
  array (
    'method' => 'GET',
    'path' => '/api/media',
    'controller' => 'TopoclimbCH\\Controllers\\MediaController',
    'action' => 'apiIndex',
    'middlewares' => 
    array (
    ),
  ),
  144 => 
  array (
    'method' => 'GET',
    'path' => '/test/regions/create',
    'controller' => 'TopoclimbCH\\Controllers\\RegionController',
    'action' => 'testCreate',
    'middlewares' => 
    array (
    ),
  ),
  145 => 
  array (
    'method' => 'GET',
    'path' => '/test/sites/create',
    'controller' => 'TopoclimbCH\\Controllers\\SiteController',
    'action' => 'form',
    'middlewares' => 
    array (
    ),
  ),
  146 => 
  array (
    'method' => 'GET',
    'path' => '/test/sectors/create',
    'controller' => 'TopoclimbCH\\Controllers\\SectorController',
    'action' => 'testCreate',
    'middlewares' => 
    array (
    ),
  ),
  147 => 
  array (
    'method' => 'GET',
    'path' => '/test/routes/create',
    'controller' => 'TopoclimbCH\\Controllers\\RouteController',
    'action' => 'testCreate',
    'middlewares' => 
    array (
    ),
  ),
  148 => 
  array (
    'method' => 'GET',
    'path' => '/test/books/create',
    'controller' => 'TopoclimbCH\\Controllers\\BookController',
    'action' => 'testCreate',
    'middlewares' => 
    array (
    ),
  ),
  149 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'dashboard',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  150 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/backups',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'backups',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  151 => 
  array (
    'method' => 'GET',
    'path' => '/api/monitoring/health',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiHealthCheck',
    'middlewares' => 
    array (
    ),
  ),
  152 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/api/system-metrics',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiSystemMetrics',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  153 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/api/usage-metrics',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiUsageMetrics',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  154 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/api/error-stats',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiErrorStats',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  155 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/record-metric',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiRecordMetric',
    'middlewares' => 
    array (
    ),
  ),
  156 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/record-error',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiRecordError',
    'middlewares' => 
    array (
    ),
  ),
  157 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/record-user-action',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiRecordUserAction',
    'middlewares' => 
    array (
    ),
  ),
  158 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/cleanup-logs',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiCleanupLogs',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  159 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/backup/full',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiCreateFullBackup',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  160 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/backup/incremental',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiCreateIncrementalBackup',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  161 => 
  array (
    'method' => 'POST',
    'path' => '/admin/monitoring/api/backup/restore/{backup_name}',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiRestoreBackup',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  162 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/api/backup/list',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiListBackups',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  163 => 
  array (
    'method' => 'GET',
    'path' => '/admin/monitoring/api/backup/stats',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'apiBackupStats',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\AdminMiddleware',
    ),
  ),
  164 => 
  array (
    'method' => 'POST',
    'path' => '/api/monitoring/webhook',
    'controller' => 'TopoclimbCH\\Controllers\\MonitoringController',
    'action' => 'webhook',
    'middlewares' => 
    array (
    ),
  ),
  165 => 
  array (
    'method' => 'GET',
    'path' => '/equipment',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  166 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/categories',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'categories',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  167 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/categories/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'createCategory',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  168 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/categories/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'createCategory',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  169 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/types',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'types',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  170 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/types/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'createType',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  171 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/types/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'createType',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
      2 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  172 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/kits',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'kits',
    'middlewares' => 
    array (
    ),
  ),
  173 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/kits/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'editKit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  174 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/kits/create',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'editKit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  175 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/kits/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'showKit',
    'middlewares' => 
    array (
    ),
  ),
  176 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/kits/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'editKit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  177 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/kits/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'editKit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  178 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/kits/{id}/duplicate',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'duplicateKit',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  179 => 
  array (
    'method' => 'POST',
    'path' => '/equipment/kits/{id}/items',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'addKitItem',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  180 => 
  array (
    'method' => 'DELETE',
    'path' => '/equipment/kits/{id}/items',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'removeKitItem',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  181 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/recommendations',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'recommendations',
    'middlewares' => 
    array (
    ),
  ),
  182 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/search',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'search',
    'middlewares' => 
    array (
    ),
  ),
  183 => 
  array (
    'method' => 'GET',
    'path' => '/equipment/kits/{id}/export',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'exportKit',
    'middlewares' => 
    array (
    ),
  ),
  184 => 
  array (
    'method' => 'GET',
    'path' => '/api/equipment/types/search',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'apiSearchTypes',
    'middlewares' => 
    array (
    ),
  ),
  185 => 
  array (
    'method' => 'GET',
    'path' => '/api/equipment/types/select',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'apiTypesForSelect',
    'middlewares' => 
    array (
    ),
  ),
  186 => 
  array (
    'method' => 'GET',
    'path' => '/api/equipment/stats',
    'controller' => 'TopoclimbCH\\Controllers\\EquipmentController',
    'action' => 'apiStats',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  187 => 
  array (
    'method' => 'GET',
    'path' => '/checklists',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'index',
    'middlewares' => 
    array (
    ),
  ),
  188 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/templates',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'templates',
    'middlewares' => 
    array (
    ),
  ),
  189 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/templates/create',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'editTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  190 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/templates/create',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'editTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  191 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/templates/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'showTemplate',
    'middlewares' => 
    array (
    ),
  ),
  192 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/templates/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'editTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  193 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/templates/{id}/edit',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'editTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  194 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/templates/{id}/create',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'createFromTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  195 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/templates/{id}/create',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'createFromTemplate',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  196 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/my',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'myChecklists',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  197 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/my/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'showChecklist',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  198 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/my/{id}/complete',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'completeChecklist',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  199 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/my/{id}/reset',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'resetChecklist',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  200 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/my/{id}/duplicate',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'duplicateChecklist',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\CsrfMiddleware',
    ),
  ),
  201 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/my/{id}/items',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'addChecklistItem',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  202 => 
  array (
    'method' => 'POST',
    'path' => '/checklists/items/{id}/toggle',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'toggleChecklistItem',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  203 => 
  array (
    'method' => 'PUT',
    'path' => '/checklists/items/{id}/notes',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'updateItemNotes',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  204 => 
  array (
    'method' => 'DELETE',
    'path' => '/checklists/items/{id}',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'removeChecklistItem',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  205 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/search',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'search',
    'middlewares' => 
    array (
    ),
  ),
  206 => 
  array (
    'method' => 'GET',
    'path' => '/checklists/my/{id}/export',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'exportChecklist',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  207 => 
  array (
    'method' => 'GET',
    'path' => '/api/checklists/equipment-types',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'apiEquipmentTypes',
    'middlewares' => 
    array (
    ),
  ),
  208 => 
  array (
    'method' => 'GET',
    'path' => '/api/checklists/stats',
    'controller' => 'TopoclimbCH\\Controllers\\ChecklistController',
    'action' => 'apiStats',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
      1 => 'TopoclimbCH\\Middleware\\PermissionMiddleware',
    ),
  ),
  209 => 
  array (
    'method' => 'GET',
    'path' => '/api/sync/offline-data',
    'controller' => 'TopoclimbCH\\Controllers\\SyncController',
    'action' => 'getOfflineData',
    'middlewares' => 
    array (
    ),
  ),
  210 => 
  array (
    'method' => 'GET',
    'path' => '/api/sync/delta',
    'controller' => 'TopoclimbCH\\Controllers\\SyncController',
    'action' => 'getDeltaSync',
    'middlewares' => 
    array (
    ),
  ),
  211 => 
  array (
    'method' => 'POST',
    'path' => '/api/sync/changes',
    'controller' => 'TopoclimbCH\\Controllers\\SyncController',
    'action' => 'syncLocalChanges',
    'middlewares' => 
    array (
      0 => 'TopoclimbCH\\Middleware\\AuthMiddleware',
    ),
  ),
  212 => 
  array (
    'method' => 'GET',
    'path' => '/api/sync/stats',
    'controller' => 'TopoclimbCH\\Controllers\\SyncController',
    'action' => 'getSyncStats',
    'middlewares' => 
    array (
    ),
  ),
  213 => 
  array (
    'method' => 'GET',
    'path' => '/map-new',
    'controller' => 'TopoclimbCH\\Controllers\\MapNewController',
    'action' => 'index',
  ),
);
