<?php

declare(strict_types=1);

namespace Sesam\Controllers;

use Sesam\Core\Request\Request;
use Sesam\Core\Response\Response;

final class DesignController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render('design/showcase', [
            'title' => 'Système visuel SESAM',
        ]);
    }
}
