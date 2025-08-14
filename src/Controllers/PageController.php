<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;

class PageController extends BaseController
{
    public function about()
    {
        return $this->render('pages/about.twig');
    }
    
    public function terms()
    {
        return $this->render('pages/terms.twig');
    }
    
    public function privacy()
    {
        return $this->render('pages/privacy.twig');
    }
}