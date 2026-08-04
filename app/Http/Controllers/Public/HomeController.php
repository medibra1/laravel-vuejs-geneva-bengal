<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/Home', [
            'seo' => [
                'title' => SiteSetting::get('default_seo_title', 'Geneva Bengal | Éleveur de chats Bengal à Genève'),
                'description' => SiteSetting::get(
                    'default_seo_description',
                    'Élevage de chats Bengal à Genève, Suisse. Chatons en parfaite santé, élevés avec amour, disponibles à l\'adoption.',
                ),
            ],
        ]);
    }
}
