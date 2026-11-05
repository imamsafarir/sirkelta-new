<?php

namespace App\Http\Controllers;

use App\Models\WebsiteSetting;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    /**
     * Display Tentang Kami page.
     */
    public function aboutUs(): Response
    {
        $setting = WebsiteSetting::where('key', 'about_us')->first();

        return Inertia::render('Legal/AboutUs', [
            'content' => WebsiteSetting::getWithDefault('about_us'),
            'updated_at' => $setting?->updated_at?->format('d M Y') ?? date('d M Y'),
        ]);
    }

    /**
     * Display Hubungi Kami page.
     */
    public function contactUs(): Response
    {
        return Inertia::render('Legal/ContactUs', [
            'supportWhatsapp' => WebsiteSetting::getWithDefault('support_whatsapp'),
            'supportEmail' => WebsiteSetting::getWithDefault('support_email'),
            'contactAddress' => WebsiteSetting::getWithDefault('contact_address'),
            'contactHours' => WebsiteSetting::getWithDefault('contact_hours'),
            'contactDescription' => WebsiteSetting::getWithDefault('contact_description'),
        ]);
    }

    /**
     * Display Kebijakan Privasi page.
     */
    public function privacyPolicy(): Response
    {
        $setting = WebsiteSetting::where('key', 'privacy_policy')->first();

        return Inertia::render('Legal/PrivacyPolicy', [
            'content' => WebsiteSetting::getWithDefault('privacy_policy'),
            'updated_at' => $setting?->updated_at?->format('d M Y') ?? date('d M Y'),
        ]);
    }

    /**
     * Display Syarat & Ketentuan page.
     */
    public function termsOfService(): Response
    {
        $setting = WebsiteSetting::where('key', 'terms_of_service')->first();

        return Inertia::render('Legal/TermsOfService', [
            'content' => WebsiteSetting::getWithDefault('terms_of_service'),
            'updated_at' => $setting?->updated_at?->format('d M Y') ?? date('d M Y'),
        ]);
    }

    /**
     * Display Kebijakan Pengembalian Dana (Refund Policy) page.
     */
    public function refundPolicy(): Response
    {
        $setting = WebsiteSetting::where('key', 'refund_policy')->first();

        return Inertia::render('Legal/RefundPolicy', [
            'content' => WebsiteSetting::getWithDefault('refund_policy'),
            'updated_at' => $setting?->updated_at?->format('d M Y') ?? date('d M Y'),
        ]);
    }
}
