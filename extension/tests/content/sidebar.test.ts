// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { WhatsAppSidebar } from '../../src/content/sidebar';
import * as api from '../../src/shared/api';

describe('WhatsApp Web Sidebar Component', () => {
  let sidebar: WhatsAppSidebar;

  beforeEach(() => {
    document.body.innerHTML = '';
    localStorage.clear();
    vi.restoreAllMocks();
    vi.spyOn(api, 'heartbeatApi').mockResolvedValue({ success: true });
    window.alert = vi.fn();

    // Mock chrome APIs
    (global as any).chrome = {
      runtime: {
        getURL: vi.fn((path) => `chrome-extension://mock-id/${path}`),
        sendMessage: vi.fn((msg, cb) => {
          if (cb) cb({ state: null });
        })
      },
      storage: {
        local: {
          get: vi.fn((keys, cb) => cb({})),
          set: vi.fn((data, cb) => cb && cb()),
          remove: vi.fn((keys, cb) => cb && cb())
        },
        onChanged: {
          addListener: vi.fn()
        }
      }
    };
  });

  afterEach(() => {
    document.body.innerHTML = '';
    delete (global as any).chrome;
    vi.restoreAllMocks();
  });

  it('mounts launcher button and sidebar root to document body', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue(null);

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const launcher = document.getElementById('wasender-launcher-btn');
    const root = document.getElementById('wasender-sidebar-root');

    expect(launcher).not.toBeNull();
    expect(root).not.toBeNull();
    expect(launcher?.textContent).toContain('WaSender');
  });

  it('toggles sidebar open and closed correctly', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue(null);

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const root = document.getElementById('wasender-sidebar-root');
    expect(root?.classList.contains('is-open')).toBe(false);

    sidebar.toggleSidebar(true);
    expect(root?.classList.contains('is-open')).toBe(true);
    expect(localStorage.getItem('wasender_sidebar_is_open')).toBe('true');

    sidebar.toggleSidebar(false);
    expect(root?.classList.contains('is-open')).toBe(false);
    expect(localStorage.getItem('wasender_sidebar_is_open')).toBe('false');
  });

  it('renders auto auth connection view when unauthenticated', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue(null);
    vi.spyOn(api, 'checkWebSessionApi').mockResolvedValue({ success: false, authenticated: false });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    expect(document.getElementById('wasender-btn-open-web-login')).not.toBeNull();
    expect(document.getElementById('wasender-btn-check-web-session')).not.toBeNull();
    expect(document.getElementById('wasender-btn-toggle-server')).toBeNull();
    expect(document.body.textContent).toContain('Masuk ke Sirkelta WaSender');
  });

  it('renders locked session notice when session is active on another device and requires logout via web', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue(null);
    vi.spyOn(api, 'checkWebSessionApi').mockResolvedValue({
      success: false,
      authenticated: true,
      code: 'SESSION_LOCKED',
      message: 'Akun aktif di komputer lain',
      active_session: { device_name: 'Chrome Laptop Kantor', ip_address: '192.168.1.1', last_heartbeat: '1 menit lalu' }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    // Takeover button is present to allow in-extension session takeover
    expect(document.getElementById('wasender-btn-takeover-session')).not.toBeNull();
    expect(document.getElementById('wasender-btn-open-web-login')).not.toBeNull();
    expect(document.body.textContent).toContain('Chrome Laptop Kantor');
    expect(document.body.textContent).toContain('Sesi Aktif di Perangkat Lain');
    expect(document.body.textContent).toContain('mengambil alih sesi');
  });

  it('renders user details, free tier badge and quota for free user', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi Santoso', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Free Starter', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 5,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 15,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    expect(document.body.textContent).toContain('Budi Santoso');
    expect(document.body.textContent).toContain('FREE');
    expect(document.body.textContent).toContain('5/20 Pesan');
    expect(document.getElementById('wasender-link-upgrade')).not.toBeNull();
  });

  it('renders pro tier badge and unlimited quota for pro user', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-pro-token',
      user: { id: 2, name: 'Siti Rahma', email: 'siti@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro-monthly', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 50,
        max_daily_messages: -1,
        is_unlimited_messages: true,
        remaining_messages: 'Unlimited',
        files_attached: 10,
        max_daily_attachments: -1,
        is_unlimited_attachments: true,
        remaining_attachments: 'Unlimited'
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    expect(document.body.textContent).toContain('Siti Rahma');
    expect(document.body.textContent).toContain('Pro Bulanan');
    expect(document.body.textContent).toContain('Unlimited (Pro)');
    expect(document.getElementById('wasender-link-upgrade')).toBeNull();
  });

  it('renders broadcast with contact book dropdown, csv download, and delay presets', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-pro-token',
      user: { id: 2, name: 'Siti', email: 'siti@test.com', role: 'user' },
      plan: { name: 'Pro', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: -1,
        is_unlimited_messages: true,
        remaining_messages: 'Unlimited',
        files_attached: 0,
        max_daily_attachments: -1,
        is_unlimited_attachments: true,
        remaining_attachments: 'Unlimited'
      }
    });

    vi.spyOn(api, 'getContactsApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, name: 'Andi', phone: '08123456789', group_name: 'Reseller' },
        { id: 2, name: 'Budi', phone: '08987654321', group_name: 'Pelanggan' }
      ]
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    // Verify broadcast elements exist
    expect(document.getElementById('wasender-select-book')).not.toBeNull();
    expect(document.getElementById('wasender-btn-download-csv-template')).not.toBeNull();
    expect(document.getElementById('wasender-btn-bc-tag-nomor')).not.toBeNull();
    expect(document.getElementById('wasender-select-delay-preset')).not.toBeNull();

    // Delay dropdown enabled for PRO user
    const delaySelect = document.getElementById('wasender-select-delay-preset') as HTMLSelectElement;
    expect(delaySelect.disabled).toBe(false);
  });

  it('locks delay dropdown to safe default for free user', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-free-token',
      user: { id: 1, name: 'Free User', email: 'free@test.com', role: 'user' },
      plan: { name: 'Free', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const delaySelect = document.getElementById('wasender-select-delay-preset') as HTMLSelectElement;
    expect(delaySelect.disabled).toBe(true);
  });

  it('switches tabs smoothly between broadcast, library, and tools', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Free', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    // Default tab is broadcast
    expect(document.getElementById('tab-broadcast')?.classList.contains('is-active')).toBe(true);

    // Switch to library tab (combined templates & contacts)
    const libTabBtn = document.querySelector('button[data-tab="library"]') as HTMLButtonElement;
    expect(libTabBtn.textContent).toContain('Kontak & Template');
    libTabBtn?.click();
    expect(document.getElementById('tab-library')?.classList.contains('is-active')).toBe(true);
    expect(document.getElementById('wasender-templates-search')).not.toBeNull();
    expect(document.getElementById('wasender-contacts-search')).not.toBeNull();

    // Verify order in library tab: Contacts first, Templates second
    const tabLibrary = document.getElementById('tab-library');
    const cards = tabLibrary?.querySelectorAll('.wasender-card');
    expect(cards?.[0].textContent).toContain('Buku Kontak Tersimpan');
    expect(cards?.[1].textContent).toContain('Template Pesan Cloud');

    // Verify manage web button is outside of cards and has green styling class
    const manageWebBtn = document.getElementById('wasender-btn-manage-web');
    expect(manageWebBtn).not.toBeNull();
    expect(manageWebBtn?.closest('.wasender-card')).toBeNull();
    expect(manageWebBtn?.classList.contains('wasender-btn-manage-green')).toBe(true);
    expect(manageWebBtn?.textContent).toContain('Kelola Kontak & Template di Web Dashboard');

    // Switch to tools tab
    const toolsTabBtn = document.querySelector('button[data-tab="tools"]') as HTMLButtonElement;
    toolsTabBtn?.click();
    expect(document.getElementById('tab-tools')?.classList.contains('is-active')).toBe(true);
  });

  it('scrapes active WhatsApp group member numbers (PRO gated)', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-pro-token',
      user: { id: 2, name: 'Pro User', email: 'pro@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: -1,
        is_unlimited_messages: true,
        remaining_messages: 'Unlimited',
        files_attached: 0,
        max_daily_attachments: -1,
        is_unlimited_attachments: true,
        remaining_attachments: 'Unlimited'
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    // Mock WhatsApp Web Main Header with group participant list
    const mainDiv = document.createElement('div');
    mainDiv.id = 'main';
    mainDiv.innerHTML = `
      <header>
        <div role="button"><span title="Komunitas Belajar Bisnis">Komunitas Belajar Bisnis</span></div>
        <span dir="auto" title="Anda, +62 812-3456-7890, 0898-7654-3210, +62 857-1122-3344">
          Anda, +62 812-3456-7890, 0898-7654-3210, +62 857-1122-3344
        </span>
      </header>
    `;
    document.body.appendChild(mainDiv);

    const scraped = sidebar.scrapeActiveGroup();
    expect(scraped.length).toBe(3);
    expect(scraped[0].phone).toBe('6281234567890');
    expect(scraped[1].phone).toBe('6289876543210');
    expect(scraped[2].phone).toBe('6285711223344');

    // Test "Ambil dari Grup" button in broadcast for PRO user
    const btnScrapeGroup = document.getElementById('wasender-btn-scrape-active-group') as HTMLButtonElement;
    expect(btnScrapeGroup).not.toBeNull();
    btnScrapeGroup.click();

    const bcContacts = (document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement).value;
    expect(bcContacts).toContain('6281234567890');
    expect(bcContacts).toContain('6289876543210');
  });

  it('blocks group scraping for Free user with PRO notice', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-free-token',
      user: { id: 1, name: 'Free User', email: 'free@test.com', role: 'user' },
      plan: { name: 'Free', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const btnScrapeGroup = document.getElementById('wasender-btn-scrape-active-group') as HTMLButtonElement;
    btnScrapeGroup.click();

    expect(window.alert).toHaveBeenCalledWith(
      expect.stringContaining('Paket PRO')
    );
  });

  it('filters templates by search query in library tab', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-pro-token',
      user: { id: 2, name: 'Pro User', email: 'pro@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: -1,
        is_unlimited_messages: true,
        remaining_messages: 'Unlimited',
        files_attached: 0,
        max_daily_attachments: -1,
        is_unlimited_attachments: true,
        remaining_attachments: 'Unlimited'
      }
    });

    vi.spyOn(api, 'getTemplatesApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, title: 'Promo Diskon Gajian', content: 'Promo gajian 50%', category: 'Promo' },
        { id: 2, title: 'Pengingat Tagihan', content: 'Halo jangan lupa bayar tagihan', category: 'Keuangan' }
      ]
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();
    await new Promise((r) => setTimeout(r, 50));

    // Switch to library tab
    const libTabBtn = document.querySelector('button[data-tab="library"]') as HTMLButtonElement;
    libTabBtn?.click();

    const tplSearchInput = document.getElementById('wasender-templates-search') as HTMLInputElement;
    const tplContainer = document.getElementById('wasender-templates-container');

    expect(tplContainer?.textContent).toContain('Promo Diskon Gajian');
    expect(tplContainer?.textContent).toContain('Pengingat Tagihan');

    // Filter for "Promo"
    tplSearchInput.value = 'Promo';
    tplSearchInput.dispatchEvent(new Event('input'));

    expect(tplContainer?.textContent).toContain('Promo Diskon Gajian');
    expect(tplContainer?.textContent).not.toContain('Pengingat Tagihan');
  });

  it('saves broadcast contacts to contact book via saveContactsApi for pro user', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-pro-token',
      user: { id: 1, name: 'Budi Pro', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    const saveSpy = vi.spyOn(api, 'saveContactsApi').mockResolvedValue({
      success: true,
      message: 'Berhasil menyimpan 2 kontak ke grup Reseller.',
      saved_count: 2,
      total: 2
    });

    vi.spyOn(api, 'getContactsApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, name: 'Andi', phone: '08123456789', group_name: 'Reseller' },
        { id: 2, name: 'Dewi', phone: '08987654321', group_name: 'Reseller' }
      ]
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();
    // Wait for background fetchCloudData to finish initial render
    await new Promise((r) => setTimeout(r, 50));

    // Populate contacts in textarea
    const contactsInput = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    contactsInput.value = '08123456789, Andi\n08987654321, Dewi';
    contactsInput.dispatchEvent(new Event('input'));

    const groupInput = document.getElementById('wasender-input-save-group-name') as HTMLInputElement;
    groupInput.value = 'Reseller';

    const btnSave = document.getElementById('wasender-btn-save-contacts-to-book') as HTMLButtonElement;
    expect(btnSave).not.toBeNull();
    btnSave.click();

    // Wait for saveContactsApi async call to complete
    await new Promise((r) => setTimeout(r, 50));

    expect(saveSpy).toHaveBeenCalledWith(
      [
        { phone: '628123456789', name: 'Andi' },
        { phone: '628987654321', name: 'Dewi' }
      ],
      'Reseller'
    );

    const statusMsg = document.getElementById('wasender-save-contacts-status');
    expect(statusMsg?.textContent).toContain('Berhasil menyimpan 2 kontak');
  });

  it('shows PRO badge and blocks saving when free tier contact limit is reached', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-free-token',
      user: { id: 1, name: 'Budi Free', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Free Starter', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    const saveSpy = vi.spyOn(api, 'saveContactsApi');

    vi.spyOn(api, 'getContactsApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, name: 'Existing Contact', phone: '08123456789', group_name: 'Umum' }
      ]
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();
    await new Promise((r) => setTimeout(r, 50));

    const btnSave = document.getElementById('wasender-btn-save-contacts-to-book') as HTMLButtonElement;
    expect(btnSave).not.toBeNull();
    // Button must contain PRO badge because free tier limit (1 contact) is reached
    expect(btnSave.innerHTML).toContain('wasender-pro-badge');
    expect(btnSave.textContent).toContain('PRO');

    const cardTitle = document.querySelector('#wasender-save-contacts-card .wasender-save-card-title');
    expect(cardTitle?.innerHTML).toContain('wasender-pro-badge');

    // Click should be blocked and not invoke saveContactsApi
    btnSave.click();
    await new Promise((r) => setTimeout(r, 50));

    expect(saveSpy).not.toHaveBeenCalled();
    const statusMsg = document.getElementById('wasender-save-contacts-status');
    expect(statusMsg?.textContent).toContain('Batas simpan kontak paket Free tercapai');
  });

  it('limits free tier to 1 template and 1 contact in library tab and shows PRO badge on delay title', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-free-token',
      user: { id: 3, name: 'Free User', email: 'free@test.com', role: 'user' },
      plan: { name: 'Free Starter', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    vi.spyOn(api, 'getTemplatesApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, title: 'Template 1', content: 'Halo 1', category: 'Umum' },
        { id: 2, title: 'Template 2', content: 'Halo 2', category: 'Umum' },
        { id: 3, title: 'Template 3', content: 'Halo 3', category: 'Umum' }
      ]
    });

    vi.spyOn(api, 'getContactsApi').mockResolvedValue({
      success: true,
      data: [
        { id: 1, name: 'Kontak A', phone: '0811111111', group_name: 'Grup 1' },
        { id: 2, name: 'Kontak B', phone: '0822222222', group_name: 'Grup 2' },
        { id: 3, name: 'Kontak C', phone: '0833333333', group_name: 'Grup 3' }
      ]
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();
    await new Promise((r) => setTimeout(r, 50));

    // Verify PRO badge on Keamanan & Jeda Waktu title for Free tier
    expect(document.body.textContent).toContain('Keamanan & Jeda Waktu');
    const delayTitle = Array.from(document.querySelectorAll('.wasender-card-title')).find((el) =>
      el.textContent?.includes('Keamanan & Jeda Waktu')
    );
    expect(delayTitle?.innerHTML).toContain('wasender-pro-badge');
    expect(delayTitle?.textContent).toContain('PRO');

    // Switch to Library tab
    const libTabBtn = document.querySelector('button[data-tab="library"]') as HTMLButtonElement;
    libTabBtn?.click();

    const tplContainer = document.getElementById('wasender-templates-container');
    const contactsContainer = document.getElementById('wasender-contacts-container');

    // Free user should only see exactly 1 template and 1 contact rendered
    expect(tplContainer?.querySelectorAll('.wasender-template-item').length).toBe(1);
    expect(contactsContainer?.querySelectorAll('.wasender-contact-item').length).toBe(1);

    expect(document.body.textContent).toContain('Paket Free hanya dapat mengakses 1 template tersimpan');
    expect(document.body.textContent).toContain('Paket Free hanya dapat mengakses 1 kontak buku');
  });

  it('clicking upgrade link dispatches OPEN_TAB message via chrome.runtime', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-free-token',
      user: { id: 3, name: 'Free User', email: 'free@test.com', role: 'user' },
      plan: { name: 'Free Starter', slug: 'free', is_free: true, duration_days: 0 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 20,
        is_unlimited_messages: false,
        remaining_messages: 20,
        files_attached: 0,
        max_daily_attachments: 20,
        is_unlimited_attachments: false,
        remaining_attachments: 20
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const upgradeLink = document.getElementById('wasender-link-upgrade') as HTMLAnchorElement;
    expect(upgradeLink).not.toBeNull();
    upgradeLink.click();

    // Verify chrome.runtime.sendMessage was dispatched with OPEN_TAB and subscription url
    await new Promise((r) => setTimeout(r, 20));
    expect((global as any).chrome.runtime.sendMessage).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'OPEN_TAB',
        payload: expect.objectContaining({
          url: expect.stringContaining('/profile')
        })
      })
    );
  });

  it('shows screen lock shield when queue is RUNNING and removes it when PAUSED/COMPLETED', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    // Initially no shield
    expect(document.getElementById('wasender-screen-shield')).toBeNull();

    // Trigger queue running update
    (sidebar as any).updateQueueProgressUI({
      runStatus: 'RUNNING',
      template: 'Halo',
      contacts: [
        { id: '1', phone: '08123', name: 'Andi', status: 'SENDING' },
        { id: '2', phone: '08124', name: 'Budi', status: 'PENDING' }
      ],
      currentIndex: 0,
      settings: { minDelaySec: 5, maxDelaySec: 10, minTypingSpeedMs: 50, maxTypingSpeedMs: 120 }
    });

    const shield = document.getElementById('wasender-screen-shield');
    expect(shield).not.toBeNull();
    expect(shield?.textContent).toContain('Broadcast Sedang Berjalan (1 / 2)');
    expect(shield?.textContent).toContain('Tampilan WhatsApp dikunci sementara');

    // Trigger queue cooldown state
    (sidebar as any).updateQueueProgressUI({
      runStatus: 'RUNNING',
      isCooldown: true,
      cooldownRemainingSec: 28,
      template: 'Halo',
      contacts: [
        { id: '1', phone: '08123', name: 'Andi', status: 'SUCCESS' },
        { id: '2', phone: '08124', name: 'Budi', status: 'PENDING' }
      ],
      currentIndex: 1,
      settings: { minDelaySec: 5, maxDelaySec: 10, minTypingSpeedMs: 50, maxTypingSpeedMs: 120 }
    });

    expect(document.getElementById('wasender-screen-shield')?.textContent).toContain('Jeda Istirahat Akun (28s)');

    // Trigger pause -> shield should be removed
    (sidebar as any).updateQueueProgressUI({
      runStatus: 'PAUSED',
      template: 'Halo',
      contacts: [],
      currentIndex: 1,
      settings: { minDelaySec: 5, maxDelaySec: 10, minTypingSpeedMs: 50, maxTypingSpeedMs: 120 }
    });

    expect(document.getElementById('wasender-screen-shield')).toBeNull();
  });

  it('shows large contacts safety tip when 50+ contacts are present in the textarea', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro Bulanan', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const contactsInput = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    const largeTip = document.getElementById('wasender-large-contacts-tip');
    expect(largeTip?.style.display).toBe('none');

    // Input 60 contacts
    const contactsList = Array.from({ length: 60 }, (_, i) => `081234567${i}, Kontak ${i}`).join('\n');
    contactsInput.value = contactsList;
    contactsInput.dispatchEvent(new Event('input'));

    expect(largeTip?.style.display).toBe('block');
    expect(largeTip?.textContent).toContain('Tips Ratusan Kontak (60 nomor)');
  });

  it('preserves user typed contacts and message across background auth refreshes', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const contactsInput = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    const msgInput = document.getElementById('wasender-broadcast-message') as HTMLTextAreaElement;

    // Simulate typing
    contactsInput.value = '081299998888, Jane Doe';
    contactsInput.dispatchEvent(new Event('input'));

    msgInput.value = 'Halo {nama}, ini penawaran spesial!';
    msgInput.dispatchEvent(new Event('input'));

    // Trigger refreshAuth
    await sidebar.refreshAuth();

    const contactsAfter = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    const msgAfter = document.getElementById('wasender-broadcast-message') as HTMLTextAreaElement;

    expect(contactsAfter.value).toBe('081299998888, Jane Doe');
    expect(msgAfter.value).toBe('Halo {nama}, ini penawaran spesial!');
  });

  it('retains typed drafts when switching tabs from broadcast to library and back', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const contactsInput = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    contactsInput.value = '089988776655, Alex';
    contactsInput.dispatchEvent(new Event('input'));

    // Switch to library tab
    const libBtn = document.querySelector('button[data-tab="library"]') as HTMLButtonElement;
    libBtn.click();

    // Switch back to broadcast tab
    const bcBtn = document.querySelector('button[data-tab="broadcast"]') as HTMLButtonElement;
    bcBtn.click();

    const contactsAfter = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement;
    expect(contactsAfter.value).toBe('089988776655, Alex');
  });

  it('clicking logout sets manual logout flag and prevents automatic web session sync', async () => {
    vi.spyOn(api, 'getStoredAuth').mockResolvedValue({
      token: 'mock-token',
      user: { id: 1, name: 'Budi', email: 'budi@test.com', role: 'user' },
      plan: { name: 'Pro', slug: 'pro', is_free: false, duration_days: 30 },
      quota: {
        messages_sent: 0,
        max_daily_messages: 500,
        is_unlimited_messages: true,
        remaining_messages: 500,
        files_attached: 0,
        max_daily_attachments: 500,
        is_unlimited_attachments: true,
        remaining_attachments: 500
      }
    });

    const setManualLogoutSpy = vi.spyOn(api, 'setManualLoggedOut').mockResolvedValue();
    const logoutSpy = vi.spyOn(api, 'logoutApi').mockResolvedValue({ success: true, message: 'Berhasil' });

    sidebar = new WhatsAppSidebar();
    await sidebar.init();

    const logoutBtn = document.getElementById('wasender-btn-logout') as HTMLButtonElement;
    expect(logoutBtn).not.toBeNull();
    logoutBtn.click();

    await new Promise((r) => setTimeout(r, 10));
    expect(setManualLogoutSpy).toHaveBeenCalledWith(true);
    expect(logoutSpy).toHaveBeenCalled();
    expect(document.getElementById('wasender-direct-login-form')).not.toBeNull();
  });
});
