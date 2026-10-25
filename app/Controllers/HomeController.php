<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('public/home', [
            'title' => 'SESAM — Sites et outils pour commerces',
            'current' => 'accueil',
        ], 200, 'layouts/public');
    }
}
