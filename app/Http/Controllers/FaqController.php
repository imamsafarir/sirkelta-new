<?php

namespace App\Http\Controllers;

use App\Models\WebsiteFaq;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    /**
     * Display the public FAQ page.
     */
    public function index(): Response
    {
        $faqs = WebsiteFaq::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();

        return Inertia::render('Faq', [
            'faqs' => $faqs,
        ]);
    }
}
