<?php

namespace TopoclimbCH\Helpers;

use Symfony\Component\HttpFoundation\Request;

/**
 * Classe d'aide pour faciliter la transition depuis
 * la classe Request personnalisée vers Symfony HttpFoundation
 */
class RequestHelper
{
    /**
     * Récupère la valeur d'un paramètre POST (équivalent à getPost)
     */
    public static function getPost(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->request->get($key, $default);
    }
    
    /**
     * Récupère tous les paramètres POST (équivalent à getAllPost)
     */
    public static function getAllPost(Request $request): array
    {
        return $request->request->all();
    }
    
    /**
     * Récupère la valeur d'un paramètre GET (équivalent à getQuery)
     */
    public static function getQuery(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->query->get($key, $default);
    }
    
    /**
     * Récupère tous les paramètres GET (équivalent à getAllQuery)
     */
    public static function getAllQuery(Request $request): array
    {
        return $request->query->all();
    }
    
    /**
     * Récupère la valeur d'un paramètre d'URL (équivalent à getParam)
     */
    public static function getParam(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->attributes->get($key, $default);
    }
    
    /**
     * Récupère tous les paramètres d'URL (équivalent à getAllParams)
     */
    public static function getAllParams(Request $request): array
    {
        return $request->attributes->all();
    }
    
    /**
     * Vérifie si la requête est de type AJAX (équivalent à isAjax)
     */
    public static function isAjax(Request $request): bool
    {
        return $request->isXmlHttpRequest();
    }
    
    /**
     * Vérifie si la méthode HTTP est GET (équivalent à isGet)
     */
    public static function isGet(Request $request): bool
    {
        return $request->isMethod('GET');
    }
    
    /**
     * Vérifie si la méthode HTTP est POST (équivalent à isPost)
     */
    public static function isPost(Request $request): bool
    {
        return $request->isMethod('POST');
    }
}