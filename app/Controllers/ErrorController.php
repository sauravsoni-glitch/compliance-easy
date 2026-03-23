<?php
namespace App\Controllers;

use App\Core\BaseController;

class ErrorController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('error/404', ['pageTitle' => 'Not Found', 'withLayout' => false], false);
    }
}
