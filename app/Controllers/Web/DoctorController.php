<?php
namespace App\Controllers\Web;

class DoctorController
{
    public function index()
    {
        ob_start();
        include __DIR__ . '/../../../views/pages/doctors.php';
        $content = ob_get_clean();

        include __DIR__ . '/../../../views/layouts/master.php';
    }
}
