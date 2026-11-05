<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteFaq;
use App\Models\WebsiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminWebsiteController extends Controller
{
    /**
     * Display website settings, pages content management, and FAQs.
     */
    public function index(Request $request): Response
    {
        $settings = [
            'support_whatsapp' => WebsiteSetting::getWithDefault('support_whatsapp'),
            'support_email' => WebsiteSetting::getWithDefault('support_email'),
            'contact_address' => WebsiteSetting::getWithDefault('contact_address'),
            'contact_hours' => WebsiteSetting::getWithDefault('contact_hours'),
            'contact_description' => WebsiteSetting::getWithDefault('contact_description'),
            'referral_discount_amount' => (int) WebsiteSetting::get('referral_discount_amount', 25000),
            'resolved_complaints_count' => WebsiteSetting::getResolvedComplaintsCount(),
        ];

        $legalPages = [
            'about_us' => [
                'title' => 'Tentang Kami',
                'description' => 'Konten profil ekosistem, visi misi, dan modul yang tampil di /tentang-kami.',
                'content' => WebsiteSetting::getWithDefault('about_us'),
                'updated_at' => WebsiteSetting::where('key', 'about_us')->first()?->updated_at?->format('d M Y H:i'),
            ],
            'privacy_policy' => [
                'title' => 'Kebijakan Privasi',
                'description' => 'Pasal perlindungan data dan privasi pengguna yang tampil di /kebijakan-privasi.',
                'content' => WebsiteSetting::getWithDefault('privacy_policy'),
                'updated_at' => WebsiteSetting::where('key', 'privacy_policy')->first()?->updated_at?->format('d M Y H:i'),
            ],
            'terms_of_service' => [
                'title' => 'Syarat & Ketentuan',
                'description' => 'Ketentuan layanan, lisensi akun, dan aturan referral yang tampil di /syarat-ketentuan.',
                'content' => WebsiteSetting::getWithDefault('terms_of_service'),
                'updated_at' => WebsiteSetting::where('key', 'terms_of_service')->first()?->updated_at?->format('d M Y H:i'),
            ],
            'refund_policy' => [
                'title' => 'Pengembalian Dana (Refund)',
                'description' => 'Ketentuan garansi 7 hari dan pengembalian potongan pro yang tampil di /kebijakan-pengembalian-dana.',
                'content' => WebsiteSetting::getWithDefault('refund_policy'),
                'updated_at' => WebsiteSetting::where('key', 'refund_policy')->first()?->updated_at?->format('d M Y H:i'),
            ],
        ];

        $faqs = WebsiteFaq::orderBy('order', 'asc')->latest()->get();

        $paymentSettings = [
            'payment_manual_enabled' => (bool) ((int) WebsiteSetting::get('payment_manual_enabled', '1')),
            'payment_bank_name' => WebsiteSetting::getWithDefault('payment_bank_name'),
            'payment_bank_account_number' => WebsiteSetting::getWithDefault('payment_bank_account_number'),
            'payment_bank_account_holder' => WebsiteSetting::getWithDefault('payment_bank_account_holder'),
            'payment_qris_merchant_name' => WebsiteSetting::getWithDefault('payment_qris_merchant_name'),
            'payment_qris_image_path' => WebsiteSetting::get('payment_qris_image_path') ? asset(WebsiteSetting::get('payment_qris_image_path')) : null,
            'payment_midtrans_enabled' => (bool) ((int) WebsiteSetting::get('payment_midtrans_enabled', '0')),
            'payment_midtrans_mode' => WebsiteSetting::get('payment_midtrans_mode', 'sandbox'),
            'payment_midtrans_client_key' => WebsiteSetting::get('payment_midtrans_client_key', ''),
            'payment_midtrans_server_key' => WebsiteSetting::get('payment_midtrans_server_key', ''),
            'payment_midtrans_merchant_id' => WebsiteSetting::get('payment_midtrans_merchant_id', ''),
        ];

        return Inertia::render('Admin/Website/Index', [
            'settings' => $settings,
            'paymentSettings' => $paymentSettings,
            'legalPages' => $legalPages,
            'faqs' => $faqs,
            'activeTab' => $request->query('tab', 'faqs'),
        ]);
    }

    /**
     * Update payment gateway and manual payment method settings.
     */
    public function updatePaymentSettings(Request $request): RedirectResponse
    {
        $request->merge([
            'payment_manual_enabled' => filter_var($request->input('payment_manual_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'payment_midtrans_enabled' => filter_var($request->input('payment_midtrans_enabled'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);

        $validated = $request->validate([
            'payment_manual_enabled' => ['required', 'boolean'],
            'payment_bank_name' => ['nullable', 'string', 'max:100'],
            'payment_bank_account_number' => ['nullable', 'string', 'max:100'],
            'payment_bank_account_holder' => ['nullable', 'string', 'max:150'],
            'payment_qris_merchant_name' => ['nullable', 'string', 'max:150'],
            'qris_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'payment_midtrans_enabled' => ['required', 'boolean'],
            'payment_midtrans_mode' => ['required', 'in:sandbox,production'],
            'payment_midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'payment_midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'payment_midtrans_merchant_id' => ['nullable', 'string', 'max:100'],
        ], [
            'payment_manual_enabled.required' => 'Status pembayaran manual wajib diisi.',
            'payment_midtrans_enabled.required' => 'Status pembayaran Midtrans wajib diisi.',
            'payment_midtrans_mode.in' => 'Mode Midtrans harus berupa sandbox atau production.',
            'qris_image.image' => 'File QRIS harus berupa gambar.',
            'qris_image.max' => 'Ukuran gambar QRIS maksimal 2MB.',
        ]);

        WebsiteSetting::set('payment_manual_enabled', $validated['payment_manual_enabled'] ? '1' : '0');
        WebsiteSetting::set('payment_bank_name', $validated['payment_bank_name'] ?? '');
        WebsiteSetting::set('payment_bank_account_number', $validated['payment_bank_account_number'] ?? '');
        WebsiteSetting::set('payment_bank_account_holder', $validated['payment_bank_account_holder'] ?? '');
        WebsiteSetting::set('payment_qris_merchant_name', $validated['payment_qris_merchant_name'] ?? '');

        if ($request->hasFile('qris_image')) {
            $path = $request->file('qris_image')->store('payment', 'public');
            WebsiteSetting::set('payment_qris_image_path', 'storage/'.$path);
        }

        WebsiteSetting::set('payment_midtrans_enabled', $validated['payment_midtrans_enabled'] ? '1' : '0');
        WebsiteSetting::set('payment_midtrans_mode', $validated['payment_midtrans_mode']);
        WebsiteSetting::set('payment_midtrans_client_key', $validated['payment_midtrans_client_key'] ?? '');
        WebsiteSetting::set('payment_midtrans_server_key', $validated['payment_midtrans_server_key'] ?? '');
        WebsiteSetting::set('payment_midtrans_merchant_id', $validated['payment_midtrans_merchant_id'] ?? '');

        return back()->with('success', 'Pengaturan metode pembayaran berhasil disimpan.');
    }

    /**
     * Update website settings & contact channels.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'support_whatsapp' => ['required', 'string', 'max:50'],
            'support_email' => ['required', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'contact_hours' => ['nullable', 'string', 'max:255'],
            'contact_description' => ['nullable', 'string'],
            'referral_discount_amount' => ['required', 'numeric', 'min:0'],
        ], [
            'support_whatsapp.required' => 'Nomor WhatsApp resmi wajib diisi.',
            'support_email.required' => 'Alamat email dukungan wajib diisi.',
            'support_email.email' => 'Format alamat email dukungan tidak valid.',
            'referral_discount_amount.required' => 'Nominal saldo potongan referral wajib diisi.',
            'referral_discount_amount.numeric' => 'Nominal saldo potongan referral harus berupa angka.',
            'referral_discount_amount.min' => 'Nominal saldo potongan referral tidak boleh kurang dari Rp 0.',
        ]);

        WebsiteSetting::set('support_whatsapp', $validated['support_whatsapp']);
        WebsiteSetting::set('support_email', $validated['support_email']);
        WebsiteSetting::set('contact_address', $validated['contact_address'] ?? 'Indonesia');
        WebsiteSetting::set('contact_hours', $validated['contact_hours'] ?? 'Senin - Sabtu: 08.00 - 18.00 WIB');
        WebsiteSetting::set('contact_description', $validated['contact_description'] ?? '');
        WebsiteSetting::set('referral_discount_amount', $validated['referral_discount_amount']);

        return back()->with('success', 'Pengaturan kontak dan referral website berhasil diperbarui.');
    }

    /**
     * Update legal / informational page content.
     */
    public function updatePage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_key' => ['required', 'string', 'in:about_us,privacy_policy,terms_of_service,refund_policy'],
            'content' => ['required', 'string'],
        ], [
            'page_key.required' => 'Kunci halaman wajib diisi.',
            'page_key.in' => 'Halaman yang dipilih tidak valid.',
            'content.required' => 'Isi konten halaman tidak boleh kosong.',
        ]);

        WebsiteSetting::set($validated['page_key'], $validated['content']);

        $titles = [
            'about_us' => 'Tentang Kami',
            'privacy_policy' => 'Kebijakan Privasi',
            'terms_of_service' => 'Syarat & Ketentuan',
            'refund_policy' => 'Pengembalian Dana (Refund)',
        ];

        $pageTitle = $titles[$validated['page_key']] ?? 'Halaman';

        return back()->with('success', "Isi konten {$pageTitle} berhasil disimpan dan diperbarui.");
    }

    /**
     * Store new FAQ item.
     */
    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ], [
            'category.required' => 'Kategori FAQ wajib diisi.',
            'question.required' => 'Pertanyaan FAQ wajib diisi.',
            'answer.required' => 'Jawaban FAQ wajib diisi.',
        ]);

        WebsiteFaq::create([
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'order' => $validated['order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Pertanyaan FAQ baru berhasil ditambahkan.');
    }

    /**
     * Update FAQ item.
     */
    public function updateFaq(Request $request, WebsiteFaq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string'],
            'order' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ], [
            'category.required' => 'Kategori FAQ wajib diisi.',
            'question.required' => 'Pertanyaan FAQ wajib diisi.',
            'answer.required' => 'Jawaban FAQ wajib diisi.',
        ]);

        $faq->update([
            'category' => $validated['category'],
            'question' => $validated['question'],
            'answer' => $validated['answer'],
            'order' => $validated['order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Pertanyaan FAQ berhasil diperbarui.');
    }

    /**
     * Delete FAQ item.
     */
    public function destroyFaq(WebsiteFaq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with('success', 'FAQ berhasil dihapus.');
    }
}
