<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;

class HelpController extends BaseController
{
    public function index()
    {
        return $this->render('pages/help.twig');
    }
}