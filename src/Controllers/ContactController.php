<?php

namespace TopoclimbCH\Controllers;

use TopoclimbCH\Controllers\BaseController;

class ContactController extends BaseController
{
    public function index()
    {
        return $this->render('pages/contact.twig');
    }
    
    public function send()
    {
        // TODO: Implémenter envoi email
        $this->flash('success', 'Message envoyé avec succès!');
        return $this->redirect('/contact');
    }
}