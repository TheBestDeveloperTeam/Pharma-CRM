<?php
namespace App\Controllers\Web;

class AuthController
{
    public function login()
    {
        // Login page has no master layout
        include __DIR__ . '/../../../views/pages/login.php';
    }
}
