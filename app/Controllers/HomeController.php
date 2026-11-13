<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Starter pages. Each method returns rendered HTML from view() to the router.
 */
final class HomeController
{
    /** GET / — views/pages/home.php */
    public function index(): string
    {
        return view('home', [
            'pageTitle' => 'Welcome Light PHP',
        ]);
    }

    /** GET /about — views/pages/about.php */
    public function about(): string
    {
        return view('about', [
            'pageTitle' => 'About',
        ]);
    }

    /** GET /documentation — views/pages/documentation.php */
    public function documentation(): string
    {
        return view('documentation', [
            'pageTitle' => 'Documentation',
        ]);
    }
}
