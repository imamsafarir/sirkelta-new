import { AuthSessionData, ContactItem, QueueState, SenderSettings, AttachmentItem } from '../shared/types';
import {
  getStoredAuth,
  clearStoredAuth,
  loginApi,
  logoutApi,
  heartbeatApi,
  validateBatchApi,
  getContactsApi,
  saveContactsApi,
  getTemplatesApi,
  getApiBaseUrl,
  setApiBaseUrl,
  checkWebSessionApi,
  isExtensionContextValid,
  isManualLoggedOut,
  setManualLoggedOut
} from '../shared/api';
import { parseContactList } from '../shared/normalizer';
import { renderTemplate } from '../shared/template';

const STORAGE_SIDEBAR_OPEN = 'wasender_sidebar_is_open';
const STORAGE_PRIVACY_BLURS = 'wasender_privacy_blurs';

export class WhatsAppSidebar {
  private rootEl: HTMLElement | null = null;
  private launcherBtn: HTMLElement | null = null;
  private currentAuth: AuthSessionData | null = null;
  private sessionLockedInfo: { device_name: string; ip_address: string; last_heartbeat: string } | null = null;
  private activeTab: 'broadcast' | 'library' | 'tools' = 'broadcast';
  private isOpen: boolean = false;
  private templatesList: Array<{ id: number; title: string; content: string; category: string; has_random_code?: boolean }> = [];
  private contactsList: Array<{ id: number; name: string; phone: string; group_name: string }> = [];
  private selectedAttachments: AttachmentItem[] = [];
  private pollInterval: any = null;

  // Persistent user draft fields so input is NEVER wiped
  private draftContacts: string = '';
  private draftMessage: string = 'Halo kak {nama}, nomor Anda {nomor} sudah terdaftar.';
  private draftRandomCode: boolean = false;
  private draftDelayPreset: string = 'safe';
  private draftMinDelay: number = 5;
  private draftMaxDelay: number = 10;

  private loadDraftsFromStorage() {
    try {
      if (typeof sessionStorage === 'undefined') return;
      const c = sessionStorage.getItem('wasender_draft_contacts');
      if (c !== null) this.draftContacts = c;
      const m = sessionStorage.getItem('wasender_draft_message');
      if (m !== null) this.draftMessage = m;
      const rc = sessionStorage.getItem('wasender_draft_random_code');
      if (rc !== null) this.draftRandomCode = rc === 'true';
      const preset = sessionStorage.getItem('wasender_draft_delay_preset');
      if (preset !== null) this.draftDelayPreset = preset;
      const min = sessionStorage.getItem('wasender_draft_min_delay');
      if (min !== null) this.draftMinDelay = Number(min) || 5;
      const max = sessionStorage.getItem('wasender_draft_max_delay');
      if (max !== null) this.draftMaxDelay = Number(max) || 10;
    } catch {}
  }

  private saveDraftsToStorage() {
    try {
      if (typeof sessionStorage === 'undefined') return;
      sessionStorage.setItem('wasender_draft_contacts', this.draftContacts);
      sessionStorage.setItem('wasender_draft_message', this.draftMessage);
      sessionStorage.setItem('wasender_draft_random_code', String(this.draftRandomCode));
      sessionStorage.setItem('wasender_draft_delay_preset', this.draftDelayPreset);
      sessionStorage.setItem('wasender_draft_min_delay', String(this.draftMinDelay));
      sessionStorage.setItem('wasender_draft_max_delay', String(this.draftMaxDelay));
    } catch {}
  }

  private captureDraftState() {
    if (!this.rootEl) return;
    const cEl = this.rootEl.querySelector<HTMLTextAreaElement>('#wasender-broadcast-contacts');
    if (cEl) this.draftContacts = cEl.value;
    const mEl = this.rootEl.querySelector<HTMLTextAreaElement>('#wasender-broadcast-message');
    if (mEl) this.draftMessage = mEl.value;
    const rcEl = this.rootEl.querySelector<HTMLInputElement>('#wasender-cb-random-code');
    if (rcEl) this.draftRandomCode = rcEl.checked;
    const presetEl = this.rootEl.querySelector<HTMLSelectElement>('#wasender-select-delay-preset');
    if (presetEl) this.draftDelayPreset = presetEl.value;
    const minEl = this.rootEl.querySelector<HTMLInputElement>('#wasender-min-delay');
    if (minEl) this.draftMinDelay = Number(minEl.value) || 5;
    const maxEl = this.rootEl.querySelector<HTMLInputElement>('#wasender-max-delay');
    if (maxEl) this.draftMaxDelay = Number(maxEl.value) || 10;
    this.saveDraftsToStorage();
  }

  private isUserTypingOrFocused(): boolean {
    if (!this.rootEl || typeof document === 'undefined') return false;
    const active = document.activeElement;
    if (!active) return false;
    if (this.rootEl.contains(active)) {
      const tag = active.tagName.toLowerCase();
      if (tag === 'textarea' || tag === 'input' || tag === 'select') {
        return true;
      }
    }
    return false;
  }

  private escapeHtml(str: string): string {
    return (str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async init() {
    if (document.getElementById('wasender-sidebar-root')) return;

    // 0. Sanitize poisoned storage API URL if present
    if (isExtensionContextValid() && chrome?.storage?.local) {
      try {
        chrome.storage.local.get(['wa_sender_custom_api_url'], (res) => {
          if (res?.wa_sender_custom_api_url && typeof res.wa_sender_custom_api_url === 'string') {
            if (res.wa_sender_custom_api_url.toLowerCase().includes('wallet')) {
              chrome.storage.local.remove(['wa_sender_custom_api_url']);
            }
          }
        });
      } catch {}
    }

    // 1. Load saved preferences & drafts
    this.isOpen = localStorage.getItem(STORAGE_SIDEBAR_OPEN) === 'true';
    this.loadDraftsFromStorage();

    // 2. Load stored auth
    this.currentAuth = await getStoredAuth();

    // 3. Mount UI
    this.createLauncherButton();
    this.createSidebarPanel();
    this.applyPrivacyBlursFromStorage();

    // 4. Setup listeners
    this.setupStorageListener();

    // Periodic auth validator: ensures immediate kickout if revoked on website (only when tab active)
    this.stopPolling();
    this.pollInterval = setInterval(async () => {
      if (!isExtensionContextValid()) {
        this.destroy();
        return;
      }
      if (document.visibilityState === 'visible' && this.currentAuth?.token) {
        const hb = await heartbeatApi();
        if (!hb.success) {
          const hadAuth = this.currentAuth !== null;
          this.currentAuth = null;
          this.sessionLockedInfo = null;
          if (hadAuth) {
            await clearStoredAuth();
            if (!this.isUserTypingOrFocused()) {
              this.render();
            }
          }
        } else if (hb.quota && this.currentAuth) {
          this.currentAuth.quota = hb.quota;
          this.updateQuotaDisplay();
        }
      }
    }, 30000);

    // 5. Initial auth validation & data loading
    if (this.currentAuth?.token) {
      heartbeatApi().then(async (res) => {
        if (!res.success) {
          // Token expired or revoked: attempt auto-sync from web ONLY if user didn't logout manually
          const isManual = await isManualLoggedOut();
          if (!isManual) {
            this.autoSyncWebSession(true, false);
          }
        } else if (res.quota && this.currentAuth) {
          this.currentAuth.quota = res.quota;
          this.updateQuotaDisplay();
        }
      }).catch(() => {});
      await this.fetchCloudData(false);
    } else {
      // Auto-sync web session on load ONLY if user didn't logout manually
      const isManual = await isManualLoggedOut();
      if (!isManual) {
        await this.autoSyncWebSession(true, false);
      }
    }

    this.render();
  }

  public async autoSyncWebSession(forceTakeover = true, ignoreManualLogout = false): Promise<boolean> {
    if (!isExtensionContextValid()) return false;
    if (!ignoreManualLogout) {
      const isManual = await isManualLoggedOut();
      if (isManual) return false;
    }
    const res = await checkWebSessionApi(forceTakeover, ignoreManualLogout);
    if (res.success && res.data) {
      await setManualLoggedOut(false);
      const oldToken = this.currentAuth?.token || '';
      const newToken = res.data.token || '';
      const oldUserId = this.currentAuth?.user?.id || 0;
      const newUserId = res.data.user?.id || 0;
      const oldPlan = this.currentAuth?.plan?.slug || '';
      const newPlan = res.data.plan?.slug || '';
      const wasLocked = this.sessionLockedInfo !== null;
      this.currentAuth = res.data;
      this.sessionLockedInfo = null;

      const identityChanged = (oldUserId !== newUserId) || (oldPlan !== newPlan) || (!oldToken && Boolean(newToken)) || wasLocked;

      if (identityChanged) {
        if (!this.isUserTypingOrFocused()) {
          await this.fetchCloudData(false);
          this.render();
        }
      } else {
        this.updateQuotaDisplay();
      }
      return true;
    } else if (res.code === 'SESSION_LOCKED') {
      const wasLocked = this.sessionLockedInfo !== null;
      this.sessionLockedInfo = res.active_session || { device_name: 'Komputer Lain', ip_address: '', last_heartbeat: '' };
      if (!wasLocked || this.currentAuth !== null) {
        this.currentAuth = null;
        if (!this.isUserTypingOrFocused()) {
          this.render();
        }
      }
      return false;
    } else {
      const hadAuth = this.currentAuth !== null;
      const wasLocked = this.sessionLockedInfo !== null;
      this.currentAuth = null;
      this.sessionLockedInfo = null;
      if ((hadAuth || wasLocked) && !this.isUserTypingOrFocused()) {
        this.render();
      }
      return false;
    }
  }

  public async refreshAuth() {
    if (!isExtensionContextValid()) return;
    const newAuth = await getStoredAuth();
    const oldToken = this.currentAuth?.token || '';
    const newToken = newAuth?.token || '';
    const oldUserId = this.currentAuth?.user?.id || 0;
    const newUserId = newAuth?.user?.id || 0;
    const oldPlan = this.currentAuth?.plan?.slug || '';
    const newPlan = newAuth?.plan?.slug || '';

    const identityChanged = (oldUserId !== newUserId) || (oldPlan !== newPlan) || (!oldToken && Boolean(newToken)) || (Boolean(oldToken) && !newToken);

    this.currentAuth = newAuth;
    this.sessionLockedInfo = null;

    if (identityChanged) {
      if (!this.isUserTypingOrFocused()) {
        if (this.currentAuth?.token) {
          await this.fetchCloudData(false);
        } else {
          await this.autoSyncWebSession();
        }
        this.render();
      }
    } else if (newAuth) {
      this.updateQuotaDisplay();
    }
  }

  private async fetchCloudData(triggerRender: boolean = true) {
    if (!this.currentAuth?.token) return;
    try {
      const [contactsRes, templatesRes] = await Promise.all([
        getContactsApi(),
        getTemplatesApi()
      ]);
      let changed = false;
      if (contactsRes.success && contactsRes.data) {
        if (JSON.stringify(this.contactsList) !== JSON.stringify(contactsRes.data)) {
          this.contactsList = contactsRes.data;
          changed = true;
        }
      }
      if (templatesRes.success && templatesRes.data) {
        if (JSON.stringify(this.templatesList) !== JSON.stringify(templatesRes.data)) {
          this.templatesList = templatesRes.data;
          changed = true;
        }
      }
      if (triggerRender && changed && this.rootEl && !this.isUserTypingOrFocused()) {
        this.render();
      }
    } catch {
      // Background tolerance
    }
  }

  private createLauncherButton() {
    if (document.getElementById('wasender-launcher-btn')) return;

    const btn = document.createElement('div');
    btn.id = 'wasender-launcher-btn';
    btn.title = 'Buka Sirkelta WaSender';

    const logoUrl = chrome.runtime?.getURL ? chrome.runtime.getURL('icons/icon-48.png') : '';
    btn.innerHTML = `
      <img src="${logoUrl}" class="wasender-launcher-icon" alt="Sirkelta WaSender" />
      <span>WaSender</span>
      <span class="wasender-launcher-arrow">◀</span>
    `;

    btn.addEventListener('click', () => this.toggleSidebar());
    document.body.appendChild(btn);
    this.launcherBtn = btn;
  }

  private createSidebarPanel() {
    const root = document.createElement('div');
    root.id = 'wasender-sidebar-root';
    if (this.isOpen) {
      root.classList.add('is-open');
      document.body.classList.add('wasender-docked-mode');
      this.triggerWhatsAppResize();
    }
    document.body.appendChild(root);
    this.rootEl = root;
  }

  public toggleSidebar(forceState?: boolean) {
    if (!this.rootEl) return;
    this.isOpen = forceState !== undefined ? forceState : !this.isOpen;
    localStorage.setItem(STORAGE_SIDEBAR_OPEN, String(this.isOpen));

    if (this.isOpen) {
      this.rootEl.classList.add('is-open');
      document.body.classList.add('wasender-docked-mode');
      if (this.launcherBtn) this.launcherBtn.style.display = 'none';
      this.render();
      this.triggerWhatsAppResize();
    } else {
      this.rootEl.classList.remove('is-open');
      document.body.classList.remove('wasender-docked-mode');
      if (this.launcherBtn) this.launcherBtn.style.display = 'flex';
      this.triggerWhatsAppResize();
    }
  }

  private triggerWhatsAppResize() {
    // Notify WhatsApp Web to recalculate responsive layout columns
    window.dispatchEvent(new Event('resize'));
    setTimeout(() => {
      window.dispatchEvent(new Event('resize'));
    }, 280);
  }

  private setupStorageListener() {
    if (chrome?.storage?.onChanged) {
      chrome.storage.onChanged.addListener((changes, area) => {
        if (!isExtensionContextValid()) return;
        if (area === 'local') {
          if (changes['wa_sender_manual_logout']?.newValue === true) {
            this.currentAuth = null;
            this.sessionLockedInfo = null;
            if (!this.isUserTypingOrFocused()) {
              this.render();
            }
          }
          if (changes['wa_sender_auth_session']) {
            const oldAuth = changes['wa_sender_auth_session'].oldValue as AuthSessionData | undefined;
            const newAuth = changes['wa_sender_auth_session'].newValue as AuthSessionData | undefined;

            const oldToken = oldAuth?.token || this.currentAuth?.token || '';
            const newToken = newAuth?.token || '';
            const oldUserId = oldAuth?.user?.id || this.currentAuth?.user?.id || 0;
            const newUserId = newAuth?.user?.id || 0;
            const oldPlanSlug = oldAuth?.plan?.slug || this.currentAuth?.plan?.slug || '';
            const newPlanSlug = newAuth?.plan?.slug || '';

            const identityChanged = (oldUserId !== newUserId) || (oldPlanSlug !== newPlanSlug) || (!oldToken && Boolean(newToken)) || (Boolean(oldToken) && !newToken);

            this.currentAuth = newAuth || null;

            if (identityChanged) {
              if (!this.isUserTypingOrFocused()) {
                if (this.currentAuth?.token) {
                  this.fetchCloudData(false).then(() => this.render());
                } else {
                  this.render();
                }
              }
            } else {
              // Token & user didn't change (e.g. background heartbeat quota update)
              this.updateQuotaDisplay();
            }
          }
          if (changes['wa_sender_queue_state']) {
            this.updateQueueProgressUI(changes['wa_sender_queue_state'].newValue);
          }
        }
      });
    }
  }

  private updateQuotaDisplay() {
    if (!this.rootEl) return;
    const auth = this.currentAuth;
    if (!auth) return;
    const isPro = auth.plan && !auth.plan.is_free;
    const quotaEl = this.rootEl.querySelector('#wasender-quota-value');
    if (quotaEl && auth.quota) {
      const sentToday = auth.quota.messages_sent ?? 0;
      const maxDaily = auth.quota.max_daily_messages ?? 20;
      quotaEl.textContent = isPro ? 'Unlimited (Pro)' : `${sentToday}/${maxDaily} Pesan`;
    }
  }

  private applyPrivacyBlursFromStorage() {
    const raw = localStorage.getItem(STORAGE_PRIVACY_BLURS);
    if (!raw) return;
    try {
      const config = JSON.parse(raw);
      if (config.lastMsg) document.body.classList.add('wasender-blur-last-msg');
      if (config.names) document.body.classList.add('wasender-blur-names');
      if (config.avatars) document.body.classList.add('wasender-blur-avatars');
      if (config.media) document.body.classList.add('wasender-blur-media');
    } catch {
      // ignore JSON parse error
    }
  }

  private savePrivacyBlurs(config: { lastMsg: boolean; names: boolean; avatars: boolean; media: boolean }) {
    localStorage.setItem(STORAGE_PRIVACY_BLURS, JSON.stringify(config));
    document.body.classList.toggle('wasender-blur-last-msg', config.lastMsg);
    document.body.classList.toggle('wasender-blur-names', config.names);
    document.body.classList.toggle('wasender-blur-avatars', config.avatars);
    document.body.classList.toggle('wasender-blur-media', config.media);
  }

  private render() {
    if (!this.rootEl) return;
    this.captureDraftState();

    const logoUrl = chrome.runtime?.getURL ? chrome.runtime.getURL('icons/icon-48.png') : '';
    const auth = this.currentAuth;
    const isPro = auth?.plan && !auth.plan.is_free;
    const remainingQuota = auth?.quota?.is_unlimited_messages ? 'Unlimited' : (auth?.quota?.remaining_messages ?? 20);
    const sentToday = auth?.quota?.messages_sent ?? 0;
    const maxDaily = auth?.quota?.max_daily_messages ?? 20;

    // Contact groups for dropdown
    const contactGroups = Array.from(new Set(this.contactsList.map((c) => c.group_name || 'Umum')));
    const isContactLimitReached = !isPro && this.contactsList.length >= 1;

    this.rootEl.innerHTML = `
      <!-- TOP HEADER -->
      <div class="wasender-header">
        <div class="wasender-brand">
          <img src="${logoUrl}" class="wasender-brand-logo" alt="Logo">
          <div>
            <div class="wasender-brand-title">Sirkelta WaSender</div>
            <div class="wasender-brand-sub">WhatsApp Broadcast by Sirkelta</div>
          </div>
        </div>
        <div class="wasender-header-actions">
          <button id="wasender-btn-close" class="wasender-btn-icon" title="Tutup Sidebar">✕</button>
        </div>
      </div>

      <!-- AUTH / ACCOUNT BAR -->
      ${auth && auth.token ? `
        <div class="wasender-account-bar">
          <div class="wasender-account-info">
            <div class="wasender-account-row">
              <span class="wasender-account-name">${auth.user.name}</span>
              <span class="wasender-plan-badge ${isPro ? 'pro' : 'free'}">
                ${isPro ? '⚡ ' + auth.plan.name : 'FREE'}
              </span>
            </div>
            <div class="wasender-quota-text">
              Kuota: <strong id="wasender-quota-value">${isPro ? 'Unlimited (Pro)' : `${sentToday}/${maxDaily} Pesan`}</strong>
            </div>
          </div>
          <div class="wasender-account-actions">
            ${!isPro ? `
              <a id="wasender-link-upgrade" href="#" class="wasender-btn-upgrade">
                Upgrade Pro
              </a>
            ` : ''}
            <button id="wasender-btn-logout" class="wasender-btn-logout">Keluar</button>
          </div>
        </div>

        ${!isPro && Number(remainingQuota) <= 0 ? `
          <div style="padding: 6px 12px; background: #fffbeb; border-bottom: 1px solid #fde68a; font-size: 11px; color: #92400e;">
            ⚠️ Kuota 20 pesan tercapai. Reset pukul 00:00 atau <a href="#" class="wasender-action-upgrade" style="color: #b45309; font-weight: 700; text-decoration: underline;">Upgrade ke Pro</a>.
          </div>
        ` : ''}

        <!-- NAVIGATION TABS -->
        <div class="wasender-nav-tabs">
          <button class="wasender-tab-btn ${this.activeTab === 'broadcast' ? 'is-active' : ''}" data-tab="broadcast">
            📢 Broadcast
          </button>
          <button class="wasender-tab-btn ${this.activeTab === 'library' ? 'is-active' : ''}" data-tab="library">
            👥 Kontak & Template
          </button>
          <button class="wasender-tab-btn ${this.activeTab === 'tools' ? 'is-active' : ''}" data-tab="tools">
            🛡️ Privasi
          </button>
        </div>

        <!-- MAIN BODY CONTENT -->
        <div class="wasender-body">

          <!-- TAB 1: BROADCAST MASSAL -->
          <div id="tab-broadcast" class="wasender-tab-content ${this.activeTab === 'broadcast' ? 'is-active' : ''}">
            <!-- Contacts Input -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">👥 Daftar Penerima</span>
                <span id="wasender-broadcast-count" class="wasender-card-badge" style="background:#e2e8f0; color:#334155;">0 nomor</span>
              </div>

              <!-- Contact Actions Row -->
              <div class="wasender-toolbar-row" style="margin-bottom: 2px;">
                <select id="wasender-select-book" class="wasender-select" style="flex: 1; min-width: 130px; font-size: 11.5px; padding: 5px 7px;">
                  <option value="">-- 📖 Pilih Buku Kontak --</option>
                  ${this.contactsList.length > 0 ? `
                    <option value="__all__">Semua Kontak (${this.contactsList.length})</option>
                    ${contactGroups.map((g) => {
                      const count = this.contactsList.filter((c) => (c.group_name || 'Umum') === g).length;
                      return `<option value="${g}">Grup: ${g} (${count})</option>`;
                    }).join('')}
                  ` : '<option value="" disabled>Belum ada kontak web</option>'}
                </select>

                <button id="wasender-btn-scrape-active-group" type="button" class="wasender-btn wasender-btn-secondary wasender-btn-xs" title="Ambil nomor anggota dari grup WhatsApp yang sedang dibuka di layar">
                  👥 Ambil dari Grup ${isPro ? '' : '<span class="wasender-pro-badge">PRO</span>'}
                </button>
                <button id="wasender-btn-download-csv-template" type="button" class="wasender-btn wasender-btn-secondary wasender-btn-xs" title="Unduh contoh template CSV">
                  📥 Template CSV ${isPro ? '' : '<span class="wasender-pro-badge">PRO</span>'}
                </button>
                <button id="wasender-btn-import-csv" type="button" class="wasender-btn wasender-btn-secondary wasender-btn-xs">
                  📁 Impor ${isPro ? '' : '<span class="wasender-pro-badge">PRO</span>'}
                </button>
                <input type="file" id="wasender-csv-file" accept=".csv,.txt" style="display: none;">
                <button id="wasender-btn-clear-contacts" type="button" class="wasender-btn wasender-btn-secondary wasender-btn-xs" title="Bersihkan daftar">
                  🗑️
                </button>
              </div>

              <textarea id="wasender-broadcast-contacts" class="wasender-textarea" rows="4" placeholder="081234567890, Budi&#10;089876543210, Siti&#10;Atau pilih buku kontak / ambil dari grup di atas...">${this.escapeHtml(this.draftContacts)}</textarea>
              
              <!-- Large Contacts Safety Advice (for 50+ contacts) -->
              <div id="wasender-large-contacts-tip" style="margin-top: 5px; padding: 7px 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; font-size: 11px; color: #1e40af; line-height: 1.45; display: none;">
                💡 <strong>Tips Ratusan Kontak (<span id="wasender-tip-count">0</span> nomor):</strong> Sistem otomatis menyisipkan jeda istirahat santai berkala tiap 10 pesan untuk melindungi nomor WhatsApp Anda. Disarankan gunakan tombol <code>+ [acak]</code> variasi kata agar pesan unik.
              </div>
              
              <!-- Save to Contact Book Panel -->
              <div id="wasender-save-contacts-card" style="margin-top: 4px; padding: 10px 12px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                  <span class="wasender-save-card-title" style="font-size: 12.5px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 5px;">
                    💾 Simpan Nomor ke Buku Kontak ${isContactLimitReached ? '<span class="wasender-pro-badge">PRO</span>' : ''}
                  </span>
                  <span style="font-size: 11px; color: #64748b;">Cloud Sync</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px;">
                  <div style="display: flex; gap: 6px;">
                    <div style="flex: 1;">
                      <label class="wasender-label" style="font-size: 11.5px; margin-bottom: 2px;">Nama Grup Kontak:</label>
                      <input id="wasender-input-save-group-name" type="text" class="wasender-input" placeholder="Misal: Pelanggan, Grup WA..." list="wasender-existing-groups-list" style="font-size: 12px; padding: 6px 9px;">
                      <datalist id="wasender-existing-groups-list">
                        ${contactGroups.map((g) => `<option value="${g}">`).join('')}
                      </datalist>
                    </div>
                    <div style="flex: 1;">
                      <label class="wasender-label" style="font-size: 11.5px; margin-bottom: 2px;">Default Nama (Opsional):</label>
                      <input id="wasender-input-save-default-name" type="text" class="wasender-input" placeholder="Misal: Member Grup" style="font-size: 12px; padding: 6px 9px;">
                    </div>
                  </div>
                  <div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: 2px;">
                    <div id="wasender-save-contacts-status" style="font-size: 11.5px; color: #059669; font-weight: 600; display: none;"></div>
                    <button id="wasender-btn-save-contacts-to-book" type="button" class="wasender-btn wasender-btn-secondary wasender-btn-xs" style="padding: 6px 12px; font-size: 12px; margin-left: auto;">
                      💾 Simpan ke Buku Kontak ${isContactLimitReached ? '<span class="wasender-pro-badge">PRO</span>' : ''}
                    </button>
                  </div>
                </div>
                ${isContactLimitReached ? `
                  <div class="wasender-alert wasender-alert-warning" style="margin-top: 8px; padding: 8px 10px; font-size: 11.5px; line-height: 1.5; display: flex; align-items: flex-start; gap: 8px;">
                    <span style="font-size: 14px; line-height: 1; flex-shrink: 0; margin-top: 1px;">⚠️</span>
                    <div style="flex: 1; color: #92400e;">
                      Batas simpan kontak paket Free telah tercapai (1 kontak tersimpan). Paket <span class="wasender-pro-badge" style="display: inline-block; vertical-align: baseline; margin: 0 2px;">PRO</span> diperlukan untuk menyimpan lebih banyak kontak. <a href="#" class="wasender-action-upgrade" style="color: #b45309; font-weight: 700; text-decoration: underline;">Upgrade ke Pro</a>.
                    </div>
                  </div>
                ` : ''}
              </div>

              ${!isPro ? `
                <div style="font-size: 11px; color: #64748b; font-style: italic; margin-top: 2px;">
                  * Paket Free dibatasi maksimal 20 kontak per broadcast.
                </div>
              ` : ''}
            </div>

            <!-- Message Editor -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">✉️ Pesan Broadcast</span>
                <select id="wasender-broadcast-template-select" class="wasender-select" style="width: auto; max-width: 170px; padding: 2px 6px; font-size: 11px;">
                  <option value="">Pilih Template</option>
                  ${this.templatesList.map((t) => `<option value="${encodeURIComponent(t.content)}">${t.title}</option>`).join('')}
                </select>
              </div>
              <textarea id="wasender-broadcast-message" class="wasender-textarea" rows="3" placeholder="Halo kak {nama}, nomor Anda {nomor} sudah terdaftar...">${this.escapeHtml(this.draftMessage)}</textarea>
              
              <!-- Variable Tags: + {nama}, + {nomor}, + [acak] -->
              <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                <button type="button" class="wasender-tag-btn" id="wasender-btn-bc-tag-name">+ {nama}</button>
                <button type="button" class="wasender-tag-btn" id="wasender-btn-bc-tag-nomor">+ {nomor}</button>
                <button type="button" class="wasender-tag-btn ${isPro ? '' : 'wasender-tag-pro'}" id="wasender-btn-bc-tag-spintax" title="Teks Variasi Acak">
                  + [acak] ${isPro ? '' : '<span class="wasender-pro-badge">PRO</span>'}
                </button>
              </div>

              <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 4px; font-size: 11px;">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                  <input type="checkbox" id="wasender-cb-random-code" ${isPro ? '' : 'disabled'} ${this.draftRandomCode ? 'checked' : ''}>
                  <span>Kode acak di akhir chat</span>
                  ${!isPro ? '<span class="wasender-pro-badge">PRO</span>' : ''}
                </label>
              </div>

              <!-- Attachments Section (Free vs PRO max 10) -->
              <div style="margin-top: 6px; padding: 6px 8px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; font-size: 11px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                  <span style="font-weight: 600; color: #334155;">📎 Lampiran File / Gambar</span>
                  ${
                    !isPro
                      ? '<span class="wasender-pro-badge" style="cursor: pointer;" id="wasender-btn-attachment-locked" title="Khusus paket PRO">PRO Only</span>'
                      : `<span style="color: #64748b; font-size: 10px;" id="wasender-attachment-count-label">${this.selectedAttachments.length} / 10 file</span>`
                  }
                </div>
                ${
                  !isPro
                    ? `
                  <div style="margin-top: 4px; display: flex; align-items: center; justify-content: space-between;">
                    <span style="color: #94a3b8; font-size: 11px;">Kirim gambar/file terkunci pada paket Free.</span>
                    <button type="button" class="wasender-tag-btn wasender-tag-pro" id="wasender-btn-attachment-free-notice" style="padding: 2px 8px; cursor: pointer;">
                      🔒 Buka di PRO
                    </button>
                  </div>
                  `
                    : `
                  <div style="margin-top: 5px; display: flex; flex-direction: column; gap: 4px;">
                    <input type="file" id="wasender-file-picker" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" style="display: none;">
                    <div style="display: flex; gap: 6px; align-items: center;">
                      <button type="button" class="wasender-tag-btn" id="wasender-btn-add-attachment" style="display: flex; align-items: center; gap: 4px; cursor: pointer;">
                        <span>➕ Tambah File (Maks 10)</span>
                      </button>
                      <button type="button" class="wasender-tag-btn" id="wasender-btn-clear-attachments" style="${this.selectedAttachments.length > 0 ? '' : 'display: none;'} color: #ef4444; border-color: #fecaca; background: #fef2f2; cursor: pointer;">
                        Hapus Semua
                      </button>
                    </div>
                    <div id="wasender-attachments-list" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px;">
                      ${this.renderAttachmentChipsHtml()}
                    </div>
                  </div>
                  `
                }
              </div>

              <!-- Live Preview -->
              <div style="padding: 6px 8px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; font-size: 11px;">
                <div style="font-weight: 700; color: #047857; font-size: 10px; text-transform: uppercase;">Pratinjau Pesan:</div>
                <div id="wasender-broadcast-preview" style="color: #1e293b; margin-top: 2px; font-style: italic; white-space: pre-wrap;"></div>
              </div>
            </div>

            <!-- Anti-Ban Settings: Preset Dropdown -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">⏱️ Keamanan & Jeda Waktu ${!isPro ? '<span class="wasender-pro-badge">PRO</span>' : ''}</span>
                <span class="wasender-card-badge" style="background:${isPro ? '#ecfdf5' : '#f1f5f9'}; color:${isPro ? '#047857' : '#475569'};">
                  ${isPro ? 'PRO Pengaturan Bebas' : 'FREE Default Terkunci'}
                </span>
              </div>
              
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <label class="wasender-label">Pilihan Kecepatan / Jeda Antar Nomor</label>
                <select id="wasender-select-delay-preset" class="wasender-select" ${!isPro ? 'disabled' : ''}>
                  <option value="safe" ${this.draftDelayPreset === 'safe' ? 'selected' : ''}>🛡️ Default Aman: 5 - 10 Detik (Direkomendasikan)</option>
                  <option value="relaxed" ${this.draftDelayPreset === 'relaxed' ? 'selected' : ''}>☕ Santai / Sangat Aman: 10 - 20 Detik</option>
                  <option value="medium" ${this.draftDelayPreset === 'medium' ? 'selected' : ''}>⚡ Sedang: 3 - 5 Detik</option>
                  <option value="fast" ${this.draftDelayPreset === 'fast' ? 'selected' : ''}>🚀 Kilat: 1 - 3 Detik (Nomor Akun Lama)</option>
                  <option value="custom" ${this.draftDelayPreset === 'custom' ? 'selected' : ''}>⚙️ Kustom (Tentukan Range Detik...)</option>
                </select>

                <!-- Custom Range Row (Shown only if custom selected in Pro) -->
                <div id="wasender-custom-delay-row" style="display: ${this.draftDelayPreset === 'custom' && isPro ? 'flex' : 'none'}; align-items: center; gap: 6px; margin-top: 4px; font-size: 11px;">
                  <span>Rentang:</span>
                  <input id="wasender-min-delay" type="number" class="wasender-input" value="${this.draftMinDelay}" min="1" max="60" style="width: 55px; text-align: center;">
                  <span>s/d</span>
                  <input id="wasender-max-delay" type="number" class="wasender-input" value="${this.draftMaxDelay}" min="2" max="120" style="width: 55px; text-align: center;">
                  <span>detik</span>
                </div>

                ${!isPro ? `
                  <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                    * Akun Free terkunci pada jeda 5-10 detik untuk keamanan akun. <a href="#" class="wasender-action-upgrade" style="color: #059669; font-weight: 700; text-decoration: underline;">Upgrade ke Pro</a> untuk bebas mengatur kecepatan.
                  </div>
                ` : ''}
              </div>
            </div>

            <!-- Progress Monitor & Queue Controls -->
            <div class="wasender-card">
              <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 700;">
                <span id="wasender-queue-status">Siap Mengirim</span>
                <span id="wasender-queue-percentage" style="color: #059669; font-family: monospace;">0%</span>
              </div>
              <div class="wasender-progress-bar-bg">
                <div id="wasender-queue-progressbar" class="wasender-progress-bar-fill"></div>
              </div>
              <div class="wasender-counter-grid">
                <div>Total: <strong id="wasender-cnt-total">0</strong></div>
                <div style="color: #059669;">Sukses: <strong id="wasender-cnt-success">0</strong></div>
                <div style="color: #e11d48;">Gagal: <strong id="wasender-cnt-failed">0</strong></div>
              </div>

              <!-- Action Buttons -->
              <div style="display: flex; gap: 6px; margin-top: 4px;">
                <button id="wasender-btn-start" class="wasender-btn wasender-btn-primary" style="flex: 1;">
                  Mulai Kirim
                </button>
                <button id="wasender-btn-pause" class="wasender-btn wasender-btn-warning" style="display: none;">
                  Jeda
                </button>
                <button id="wasender-btn-resume" class="wasender-btn wasender-btn-primary" style="display: none;">
                  Lanjutkan
                </button>
                <button id="wasender-btn-stop" class="wasender-btn wasender-btn-danger" disabled>
                  Hentikan
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 2: PUSTAKA KONTAK & TEMPLATE -->
          <div id="tab-library" class="wasender-tab-content ${this.activeTab === 'library' ? 'is-active' : ''}">
            
            <!-- SECTION 1: BUKU KONTAK CLOUD -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">👥 Buku Kontak Tersimpan</span>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span id="wasender-contacts-count" class="wasender-card-badge" style="background:#eff6ff; color:#1d4ed8;">
                    ${this.contactsList.length} kontak
                  </span>
                  <button id="wasender-btn-refresh-contacts" class="wasender-btn wasender-btn-secondary wasender-btn-xs" title="Segarkan Kontak">
                    🔄
                  </button>
                </div>
              </div>

              ${!isPro ? `
                <div class="wasender-alert wasender-alert-warning" style="padding: 6px 10px; font-size: 11.5px;">
                  ℹ️ Paket Free hanya dapat mengakses 1 kontak buku. <a href="#" class="wasender-action-upgrade" style="color: #047857; font-weight: 700; text-decoration: underline;">Upgrade ke Pro</a> untuk kontak tanpa batas.
                </div>
              ` : ''}
              
              <!-- Filter & Search Toolbar -->
              <div style="display: flex; gap: 6px;">
                <select id="wasender-contacts-filter-group" class="wasender-select" style="flex: 1; font-size: 11.5px; padding: 5px 7px;">
                  <option value="__all__">Semua Grup (${this.contactsList.length})</option>
                  ${contactGroups.map((g) => {
                    const count = this.contactsList.filter((c) => (c.group_name || 'Umum') === g).length;
                    return `<option value="${g}">${g} (${count})</option>`;
                  }).join('')}
                </select>
                <input id="wasender-contacts-search" type="text" class="wasender-input" placeholder="🔍 Cari nama/nomor kontak..." style="flex: 1; font-size: 11.5px; padding: 5px 8px;">
              </div>

              <!-- Bulk Action to Broadcast -->
              <button id="wasender-btn-broadcast-all-contacts" class="wasender-btn wasender-btn-primary wasender-btn-xs" style="padding: 7px 10px; width: 100%;">
                📢 Masukkan Kontak Terpilih ke Broadcast
              </button>

              <!-- Scrollable Contacts Cards Container -->
              <div id="wasender-contacts-container" style="display: flex; flex-direction: column; gap: 6px; max-height: 250px; overflow-y: auto; padding-right: 2px;">
                ${this.renderContactsListHtml()}
              </div>
            </div>

            <!-- SECTION 2: TEMPLATE PESAN CLOUD -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">📚 Template Pesan Cloud</span>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span id="wasender-templates-count" class="wasender-card-badge" style="background:#ecfdf5; color:#047857;">
                    ${this.templatesList.length} template
                  </span>
                  <button id="wasender-btn-refresh-templates" class="wasender-btn wasender-btn-secondary wasender-btn-xs" title="Segarkan Template">
                    🔄
                  </button>
                </div>
              </div>

              <!-- Search Template Input -->
              <div style="display: flex; gap: 6px;">
                <input id="wasender-templates-search" type="text" class="wasender-input" placeholder="🔍 Cari judul atau isi template pesan..." style="font-size: 12px; padding: 6px 9px;">
              </div>

              ${!isPro ? `
                <div class="wasender-alert wasender-alert-warning" style="padding: 6px 10px; font-size: 11.5px;">
                  ℹ️ Paket Free hanya dapat mengakses 1 template tersimpan. <a href="#" class="wasender-action-upgrade" style="color: #047857; font-weight: 700; text-decoration: underline;">Upgrade ke Pro</a> untuk template tanpa batas.
                </div>
              ` : ''}

              <!-- Scrollable Templates List Container -->
              <div id="wasender-templates-container" style="display: flex; flex-direction: column; gap: 8px; max-height: 230px; overflow-y: auto; padding-right: 2px;">
                ${this.renderTemplatesListHtml(isPro)}
              </div>
            </div>

            <!-- STANDALONE ACTION BUTTON: KELOLA DI WEB DASHBOARD (DI LUAR KARTU, HIJAU MENARIK PERHATIAN) -->
            <div style="margin-top: 6px; margin-bottom: 6px;">
              <button id="wasender-btn-manage-web" type="button" class="wasender-btn wasender-btn-manage-green" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; padding: 11px 14px; font-size: 12px; border-radius: 8px; cursor: pointer;">
                🌐 Kelola Kontak & Template di Web Dashboard
              </button>
            </div>
          </div>

          <!-- TAB 3: TOOLS & PRIVASI -->
          <div id="tab-tools" class="wasender-tab-content ${this.activeTab === 'tools' ? 'is-active' : ''}">
            <!-- WhatsApp Privacy Blurs -->
            <div class="wasender-card">
              <div class="wasender-card-header">
                <span class="wasender-card-title">👁️ Privasi Layar WhatsApp</span>
              </div>
              <div style="font-size: 11.5px; color: #64748b;">
                Sembunyikan informasi sensitif saat berbagi layar (screen-sharing) atau membuka di ruang publik. Hover cursor untuk melihat.
              </div>

              <div class="wasender-switch-row">
                <div class="wasender-switch-info">
                  <span class="wasender-switch-title">Blur Pesan Terakhir</span>
                  <span class="wasender-switch-desc">Sensor cuplikan chat di daftar obrolan kiri</span>
                </div>
                <label class="wasender-toggle">
                  <input type="checkbox" id="wasender-blur-lastmsg" ${document.body.classList.contains('wasender-blur-last-msg') ? 'checked' : ''}>
                  <span class="wasender-slider"></span>
                </label>
              </div>

              <div class="wasender-switch-row">
                <div class="wasender-switch-info">
                  <span class="wasender-switch-title">Blur Nama & Kontak</span>
                  <span class="wasender-switch-desc">Sensor nama kontak dan nomor telepon</span>
                </div>
                <label class="wasender-toggle">
                  <input type="checkbox" id="wasender-blur-names" ${document.body.classList.contains('wasender-blur-names') ? 'checked' : ''}>
                  <span class="wasender-slider"></span>
                </label>
              </div>

              <div class="wasender-switch-row">
                <div class="wasender-switch-info">
                  <span class="wasender-switch-title">Blur Foto Profil</span>
                  <span class="wasender-switch-desc">Sensor foto avatar di daftar dan obrolan</span>
                </div>
                <label class="wasender-toggle">
                  <input type="checkbox" id="wasender-blur-avatars" ${document.body.classList.contains('wasender-blur-avatars') ? 'checked' : ''}>
                  <span class="wasender-slider"></span>
                </label>
              </div>

              <div class="wasender-switch-row">
                <div class="wasender-switch-info">
                  <span class="wasender-switch-title">Blur Gambar & Media</span>
                  <span class="wasender-switch-desc">Sensor foto/gambar di dalam percakapan</span>
                </div>
                <label class="wasender-toggle">
                  <input type="checkbox" id="wasender-blur-media" ${document.body.classList.contains('wasender-blur-media') ? 'checked' : ''}>
                  <span class="wasender-slider"></span>
                </label>
              </div>
            </div>
          </div>
        </div>
      ` : `
        <!-- AUTH VIEW: DIRECT LOGIN & WEB SYNC -->
        <div class="wasender-body" style="padding: 16px; overflow-y: auto;">
          <div class="wasender-card" style="padding: 20px; text-align: left; width: 100%; box-sizing: border-box;">
            <div style="text-align: center; margin-bottom: 16px;">
              <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; color: #059669; font-size: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px auto; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                🔐
              </div>
              <div style="font-weight: 700; font-size: 15px; color: #0f172a; margin-bottom: 3px;">
                Masuk ke Sirkelta WaSender
              </div>
              <div style="font-size: 11.5px; color: #64748b; line-height: 1.4;">
                Gunakan akun Sirkelta Anda untuk menghubungkan ekstensi broadcast WhatsApp.
              </div>
            </div>

            ${this.sessionLockedInfo ? `
              <!-- LOCKED SESSION WARNING -->
              <div style="padding: 12px 14px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; text-align: left; margin-bottom: 14px; font-size: 11.5px; color: #92400e; line-height: 1.5;">
                <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 5px;">
                  ⚠️ Sesi Aktif di Perangkat Lain
                </div>
                <div>Perangkat: <strong>${this.sessionLockedInfo.device_name || 'Browser Lain'}</strong></div>
                <div>Terakhir aktif: ${this.sessionLockedInfo.last_heartbeat || 'Baru saja'}</div>
                <div style="margin-top: 8px; font-size: 11px; color: #78350f; background: #fef3c7; padding: 7px 9px; border-radius: 6px;">
                  ℹ️ Akun Anda dibatasi 1 sesi aktif. Klik tombol di bawah untuk mengambil alih sesi ke browser ini.
                </div>
                <button id="wasender-btn-takeover-session" type="button" class="wasender-btn wasender-btn-warning" style="width: 100%; margin-top: 10px; padding: 9px 12px; font-size: 12px;">
                  ⚡ Ambil Alih Sesi di Sini
                </button>
              </div>
            ` : ''}

            <!-- DIRECT LOGIN FORM -->
            <form id="wasender-direct-login-form" style="display: flex; flex-direction: column; gap: 11px;">
              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Email atau Username</label>
                <input id="wasender-input-login" type="text" class="wasender-input" placeholder="contoh@sirkelta.com atau username" required autocomplete="username" />
              </div>

              <div>
                <label style="display: block; font-size: 11px; font-weight: 600; color: #334155; margin-bottom: 4px;">Kata Sandi</label>
                <input id="wasender-input-password" type="password" class="wasender-input" placeholder="••••••••" required autocomplete="current-password" />
              </div>

              <div id="wasender-login-status-msg" style="display: none; font-size: 11.5px; padding: 8px 10px; border-radius: 7px; line-height: 1.4;"></div>

              <button id="wasender-btn-submit-login" type="submit" class="wasender-btn wasender-btn-primary" style="width: 100%; padding: 10px 14px; font-size: 12.5px; margin-top: 2px;">
                🚀 Masuk ke Akun
              </button>
            </form>

            <div style="display: flex; align-items: center; gap: 8px; margin: 16px 0 14px 0;">
              <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
              <span style="font-size: 11px; color: #94a3b8; font-weight: 500;">atau sinkronisasi otomatis</span>
              <div style="flex: 1; height: 1px; background: #e2e8f0;"></div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
              <button id="wasender-btn-check-web-session" type="button" class="wasender-btn wasender-btn-secondary" style="width: 100%; padding: 9px 12px; font-size: 11.5px;">
                🔄 Cek Status Login Web
              </button>
              <div id="wasender-sync-status-msg" style="display: none; font-size: 11.5px; padding: 8px 10px; border-radius: 7px; line-height: 1.4;"></div>
              <button id="wasender-btn-open-web-login" type="button" class="wasender-btn" style="width: 100%; padding: 8px 12px; font-size: 11.5px; background: #f8fafc; color: #475569; border: 1px solid #e2e8f0;">
                🌐 Buka Dashboard Sirkelta
              </button>
            </div>

            <div style="font-size: 11px; color: #64748b; margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 12px; text-align: center;">
              Belum punya akun? <a id="wasender-link-register" href="#" style="color: #059669; font-weight: 600; text-decoration: none;">Daftar di Sirkelta ➔</a>
            </div>
          </div>
        </div>
      `}
    `;

    this.bindEvents();
    this.syncQueueState();
  }

  private renderTemplatesListHtml(isPro: boolean, filteredList?: Array<{ id: number; title: string; content: string; category: string; has_random_code?: boolean }>): string {
    const sourceList = filteredList !== undefined ? filteredList : this.templatesList;
    if (sourceList.length === 0) {
      return '<div style="font-size: 12px; color: #94a3b8; text-align: center; padding: 14px;">Tidak ada template yang cocok.</div>';
    }
    const displayList = !isPro ? sourceList.slice(0, 1) : sourceList;

    return displayList
      .map(
        (t) => `
        <div class="wasender-template-item">
          <div class="wasender-template-title-row">
            <span class="wasender-template-title">${t.title}</span>
            <span class="wasender-card-badge" style="background:#f1f5f9; color:#475569;">${t.category}</span>
          </div>
          <div class="wasender-template-content">${t.content}</div>
          <div class="wasender-template-actions">
            <button class="wasender-btn wasender-btn-primary wasender-btn-xs wasender-btn-use-broadcast" data-content="${encodeURIComponent(t.content)}">
              📢 Gunakan di Broadcast
            </button>
            <button class="wasender-btn wasender-btn-secondary wasender-btn-xs wasender-btn-copy-template" data-content="${encodeURIComponent(t.content)}">
              📋 Salin
            </button>
          </div>
        </div>
      `
      )
      .join('');
  }

  private renderContactsListHtml(filtered?: Array<{ id: number; name: string; phone: string; group_name: string }>): string {
    const list = filtered || this.contactsList;
    if (list.length === 0) {
      return '<div style="font-size: 11px; color: #94a3b8; text-align: center; padding: 12px;">Tidak ada kontak yang sesuai filter.</div>';
    }
    const isPro = this.currentAuth?.plan && !this.currentAuth.plan.is_free;
    const displayList = !isPro ? list.slice(0, 1) : list;

    return displayList
      .map((c) => {
        const initial = (c.name || 'U').charAt(0).toUpperCase();
        return `
          <div class="wasender-contact-item">
            <div class="wasender-contact-info">
              <div class="wasender-contact-avatar">${initial}</div>
              <div class="wasender-contact-details">
                <span class="wasender-contact-name">${c.name}</span>
                <span class="wasender-contact-phone">${c.phone}</span>
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 4px;">
              <span class="wasender-contact-group">${c.group_name || 'Umum'}</span>
              <button class="wasender-btn wasender-btn-secondary wasender-btn-xs wasender-btn-add-single-contact" data-line="${c.phone}, ${c.name}" title="Tambahkan ke Broadcast">
                +
              </button>
            </div>
          </div>
        `;
      })
      .join('');
  }

  private async openWebPage(path: string) {
    try {
      const baseUrl = await getApiBaseUrl();
      const webBase = baseUrl.replace(/\/api\/extension\/?$/, '');
      const url = `${webBase}${path.startsWith('/') ? path : '/' + path}`;
      if (chrome?.runtime?.sendMessage) {
        chrome.runtime.sendMessage({ type: 'OPEN_TAB', payload: { url } });
      } else {
        window.open(url, '_blank');
      }
    } catch {
      window.open(path, '_blank');
    }
  }

  private bindUpgradeLinks() {
    this.rootEl?.querySelectorAll('.wasender-action-upgrade').forEach((el) => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        this.openWebPage('/profile');
      });
    });
  }

  private async bindEvents() {
    if (!this.rootEl) return;

    // Header Close Button
    document.getElementById('wasender-btn-close')?.addEventListener('click', () => this.toggleSidebar(false));

    // Direct Login Form Submission
    const formLogin = document.getElementById('wasender-direct-login-form') as HTMLFormElement | null;
    formLogin?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const inputLogin = document.getElementById('wasender-input-login') as HTMLInputElement | null;
      const inputPass = document.getElementById('wasender-input-password') as HTMLInputElement | null;
      const btnSubmit = document.getElementById('wasender-btn-submit-login') as HTMLButtonElement | null;
      const msg = document.getElementById('wasender-login-status-msg');

      const loginVal = inputLogin?.value.trim() || '';
      const passVal = inputPass?.value || '';

      if (!loginVal || !passVal) {
        if (msg) {
          msg.textContent = 'Silakan isi username/email dan kata sandi.';
          msg.style.display = 'block';
          msg.style.background = '#fef2f2';
          msg.style.color = '#991b1b';
          msg.style.border = '1px solid #fecaca';
        }
        return;
      }

      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Memverifikasi...';
      }
      if (msg) msg.style.display = 'none';

      try {
        const res = await loginApi(loginVal, passVal, false);
        if (res.success && res.data) {
          await setManualLoggedOut(false);
          this.currentAuth = res.data;
          this.sessionLockedInfo = null;
          this.render();
          await this.fetchCloudData(false);
          this.render();
        } else if (res.code === 'SESSION_LOCKED' && res.active_session) {
          this.sessionLockedInfo = res.active_session;
          this.render();
        } else {
          if (msg) {
            msg.textContent = res.message || 'Login gagal. Periksa kembali akun Anda.';
            msg.style.display = 'block';
            msg.style.background = '#fef2f2';
            msg.style.color = '#991b1b';
            msg.style.border = '1px solid #fecaca';
          }
        }
      } catch (err: any) {
        if (msg) {
          msg.textContent = 'Terjadi kesalahan jaringan. Pastikan koneksi dan server aktif.';
          msg.style.display = 'block';
          msg.style.background = '#fef2f2';
          msg.style.color = '#991b1b';
          msg.style.border = '1px solid #fecaca';
        }
      } finally {
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.textContent = '🚀 Masuk ke Akun';
        }
      }
    });

    // Takeover Session Button
    document.getElementById('wasender-btn-takeover-session')?.addEventListener('click', async () => {
      const btn = document.getElementById('wasender-btn-takeover-session') as HTMLButtonElement | null;
      const inputLogin = document.getElementById('wasender-input-login') as HTMLInputElement | null;
      const inputPass = document.getElementById('wasender-input-password') as HTMLInputElement | null;
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Mengambil alih...';
      }
      if (inputLogin?.value && inputPass?.value) {
        const res = await loginApi(inputLogin.value.trim(), inputPass.value, true);
        if (res.success && res.data) {
          await setManualLoggedOut(false);
          this.currentAuth = res.data;
          this.sessionLockedInfo = null;
          this.render();
          await this.fetchCloudData(false);
          this.render();
          return;
        }
      }
      const ok = await this.autoSyncWebSession(true, true);
      if (ok) {
        await setManualLoggedOut(false);
      } else {
        alert('Gagal mengambil alih sesi secara otomatis. Silakan masukkan username/email dan kata sandi Anda.');
        if (btn) {
          btn.disabled = false;
          btn.textContent = '⚡ Ambil Alih Sesi di Sini';
        }
      }
    });

    // Auto Auth Connection Buttons
    document.getElementById('wasender-btn-open-web-login')?.addEventListener('click', async () => {
      try {
        const baseUrl = await getApiBaseUrl();
        const webBase = baseUrl.replace(/\/api\/extension\/?$/, '');
        chrome.runtime?.sendMessage?.({ type: 'OPEN_TAB', payload: { url: `${webBase}/login` } });
      } catch {
        chrome.runtime?.sendMessage?.({ type: 'OPEN_TAB', payload: { url: 'http://localhost:8000/login' } });
      }
    });

    document.getElementById('wasender-btn-check-web-session')?.addEventListener('click', async () => {
      const btn = document.getElementById('wasender-btn-check-web-session') as HTMLButtonElement | null;
      const msg = document.getElementById('wasender-sync-status-msg');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Memeriksa sesi...';
      }
      if (msg) msg.style.display = 'none';

      const ok = await this.autoSyncWebSession(true, true);
      if (ok) {
        await setManualLoggedOut(false);
      } else if (msg) {
        msg.textContent = this.sessionLockedInfo
          ? 'Akun terdeteksi sedang aktif di browser/komputer lain.'
          : 'Belum terdeteksi login di web. Silakan buka dashboard dan login terlebih dahulu.';
        msg.style.display = 'block';
        msg.style.background = '#fef2f2';
        msg.style.color = '#991b1b';
        msg.style.border = '1px solid #fecaca';
      }

      if (btn) {
        btn.disabled = false;
        btn.textContent = '🔄 Cek Status Login Web';
      }
    });

    // Register Link
    document.getElementById('wasender-link-register')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.openWebPage('/register');
    });

    // Upgrade Pro Link & Action Upgrade Triggers
    const onUpgradeClick = (e: Event) => {
      e.preventDefault();
      this.openWebPage('/profile');
    };
    document.getElementById('wasender-link-upgrade')?.addEventListener('click', onUpgradeClick);
    this.bindUpgradeLinks();

    // Logout
    document.getElementById('wasender-btn-logout')?.addEventListener('click', async () => {
      await setManualLoggedOut(true);
      await logoutApi();
      this.currentAuth = null;
      this.sessionLockedInfo = null;
      this.render();
    });

    // Tab Switching
    this.rootEl.querySelectorAll('.wasender-tab-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const tab = btn.getAttribute('data-tab') as 'broadcast' | 'library' | 'tools';
        if (tab) {
          this.activeTab = tab;
          this.render();
        }
      });
    });

    // If logged in, initialize active tab specifics
    if (this.currentAuth?.token) {
      if (this.activeTab === 'broadcast') {
        this.initBroadcastTab();
      } else if (this.activeTab === 'library') {
        this.initLibraryTab();
      } else if (this.activeTab === 'tools') {
        this.initToolsTab();
      }
    }
  }

  // TAB 1: Broadcast Tab Logic
  private initBroadcastTab() {
    const contactsInput = document.getElementById('wasender-broadcast-contacts') as HTMLTextAreaElement | null;
    const countBadge = document.getElementById('wasender-broadcast-count');
    const msgInput = document.getElementById('wasender-broadcast-message') as HTMLTextAreaElement | null;
    const previewBox = document.getElementById('wasender-broadcast-preview');
    const templateSelect = document.getElementById('wasender-broadcast-template-select') as HTMLSelectElement | null;
    const selectBook = document.getElementById('wasender-select-book') as HTMLSelectElement | null;
    const isPro = this.currentAuth?.plan && !this.currentAuth.plan.is_free;

    const updatePreviewAndCount = () => {
      const contacts = parseContactList(contactsInput?.value || '');
      if (countBadge) countBadge.textContent = `${contacts.length} nomor`;

      const largeTip = document.getElementById('wasender-large-contacts-tip');
      const tipCount = document.getElementById('wasender-tip-count');
      if (largeTip && tipCount) {
        if (contacts.length >= 50) {
          tipCount.textContent = String(contacts.length);
          largeTip.style.display = 'block';
        } else {
          largeTip.style.display = 'none';
        }
      }

      if (previewBox && msgInput) {
        const sampleContact = contacts[0] || { id: '0', phone: '08123456789', name: 'Budi' };
        const rendered = renderTemplate(msgInput.value, sampleContact, 'Kak');
        previewBox.textContent = rendered || '(Pesan kosong)';
      }
    };

    const onContactsInput = () => {
      this.draftContacts = contactsInput?.value || '';
      this.saveDraftsToStorage();
      updatePreviewAndCount();
    };
    const onMsgInput = () => {
      this.draftMessage = msgInput?.value || '';
      this.saveDraftsToStorage();
      updatePreviewAndCount();
    };

    contactsInput?.addEventListener('input', onContactsInput);
    msgInput?.addEventListener('input', onMsgInput);
    updatePreviewAndCount();

    // 1. Pilih Buku Kontak Dropdown
    selectBook?.addEventListener('change', () => {
      const selected = selectBook.value;
      if (!selected) return;

      let filtered = this.contactsList;
      if (selected !== '__all__') {
        filtered = this.contactsList.filter((c) => (c.group_name || 'Umum') === selected);
      }

      if (filtered.length === 0) {
        alert('Tidak ada kontak dalam pilihan tersebut.');
        return;
      }

      const contactsToAdd = !isPro ? filtered.slice(0, 1) : filtered;
      if (!isPro && filtered.length > 1) {
        alert(`Paket Free hanya dapat memasukkan 1 kontak dari buku kontak (dari total ${filtered.length} kontak). Upgrade ke Pro untuk akses seluruh kontak tanpa batas.`);
      }

      const lines = contactsToAdd.map((c) => `${c.phone}, ${c.name}`).join('\n');
      if (contactsInput) {
        contactsInput.value = contactsInput.value ? contactsInput.value + '\n' + lines : lines;
        this.draftContacts = contactsInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });

    // 1b. Ambil dari Grup Aktif (PRO Gated)
    document.getElementById('wasender-btn-scrape-active-group')?.addEventListener('click', () => {
      if (!isPro) {
        alert('Fitur Ambil Nomor dari Grup WhatsApp adalah fitur khusus Paket PRO.\n\nSilakan upgrade akun Anda ke Paket PRO untuk mengekstrak kontak grup secara otomatis.');
        return;
      }

      const scraped = this.scrapeActiveGroup();
      if (scraped.length === 0) {
        alert('Tidak ditemukan nomor anggota pada grup WhatsApp aktif.\n\nTips:\n1. Pastikan Anda sedang membuka percakapan grup WhatsApp di layar utama.\n2. Jika nomor anggota tidak langsung terbaca di subjudul header, klik header atas obrolan WhatsApp untuk membuka panel "Info Grup" agar seluruh kontak termuat.');
        return;
      }

      const lines = scraped.map((c) => `${c.phone}, ${c.name}`).join('\n');
      if (contactsInput) {
        contactsInput.value = contactsInput.value ? contactsInput.value + '\n' + lines : lines;
        this.draftContacts = contactsInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }

      const saveGroupInput = document.getElementById('wasender-input-save-group-name') as HTMLInputElement | null;
      if (saveGroupInput && !saveGroupInput.value) {
        const groupTitle = this.getActiveChatTitle();
        if (groupTitle) saveGroupInput.value = groupTitle;
      }

      alert(`Berhasil mengambil ${scraped.length} nomor dari grup aktif!`);
    });

    // 1c. Simpan ke Buku Kontak Web
    const btnSaveContacts = document.getElementById('wasender-btn-save-contacts-to-book') as HTMLButtonElement | null;
    const inputSaveGroupName = document.getElementById('wasender-input-save-group-name') as HTMLInputElement | null;
    const inputSaveDefaultName = document.getElementById('wasender-input-save-default-name') as HTMLInputElement | null;
    const saveStatusMsg = document.getElementById('wasender-save-contacts-status');

    btnSaveContacts?.addEventListener('click', async () => {
      if (!isPro && this.contactsList.length >= 1) {
        if (saveStatusMsg) {
          saveStatusMsg.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 6px; line-height: 1.45; text-align: left;">
              <span style="flex-shrink: 0;">⚠️</span>
              <div>Batas simpan kontak paket Free tercapai (1 kontak). Paket <span class="wasender-pro-badge">PRO</span> diperlukan. <a href="#" class="wasender-action-upgrade" style="color: #e11d48; text-decoration: underline; font-weight: bold;">Upgrade ke Pro</a></div>
            </div>
          `;
          saveStatusMsg.style.display = 'block';
          saveStatusMsg.style.color = '#e11d48';
          this.bindUpgradeLinks();
        }
        alert('Batas penyimpanan kontak paket Free telah tercapai (maksimal 1 kontak).\n\nSilakan upgrade ke Paket PRO untuk menyimpan kontak tanpa batas ke buku kontak.');
        return;
      }

      const contacts = parseContactList(contactsInput?.value || '');
      if (contacts.length === 0) {
        alert('Daftar nomor penerima masih kosong. Masukkan nomor kontak terlebih dahulu.');
        return;
      }

      const groupName = inputSaveGroupName?.value.trim() || 'Umum';
      const defaultPrefix = inputSaveDefaultName?.value.trim() || '';

      const contactsToSave = contacts.map((c, idx) => {
        let name = c.name;
        if (!name || name === c.phone || name.startsWith('+')) {
          name = defaultPrefix ? `${defaultPrefix} ${idx + 1}` : `Kontak ${c.phone.slice(-4)}`;
        }
        return {
          phone: c.phone,
          name: name
        };
      });

      btnSaveContacts.disabled = true;
      btnSaveContacts.textContent = 'Menyimpan...';
      if (saveStatusMsg) saveStatusMsg.style.display = 'none';

      const res = await saveContactsApi(contactsToSave, groupName);
      btnSaveContacts.disabled = false;
      const reachedAfter = !isPro && (this.contactsList.length >= 1 || res.code === 'QUOTA_EXCEEDED');
      btnSaveContacts.innerHTML = `💾 Simpan ke Buku Kontak ${reachedAfter ? '<span class="wasender-pro-badge">PRO</span>' : ''}`;

      if (res.success) {
        if (saveStatusMsg) {
          saveStatusMsg.textContent = `✓ ${res.message || 'Kontak berhasil disimpan!'}`;
          saveStatusMsg.style.display = 'block';
          saveStatusMsg.style.color = '#059669';
        }
        // Refresh local contacts list and cloud data
        const refreshRes = await getContactsApi();
        if (refreshRes.success && refreshRes.data) {
          this.contactsList = refreshRes.data;
          this.refreshContactBookDropdown();
          if (!isPro && this.contactsList.length >= 1) {
            btnSaveContacts.innerHTML = '💾 Simpan ke Buku Kontak <span class="wasender-pro-badge">PRO</span>';
            const cardTitle = document.querySelector('#wasender-save-contacts-card .wasender-save-card-title');
            if (cardTitle && !cardTitle.innerHTML.includes('wasender-pro-badge')) {
              cardTitle.innerHTML = '💾 Simpan Nomor ke Buku Kontak <span class="wasender-pro-badge">PRO</span>';
            }
          }
        }
      } else {
        if (saveStatusMsg) {
          const isQuota = res.code === 'QUOTA_EXCEEDED' || res.message?.includes('Batas');
          saveStatusMsg.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 6px; line-height: 1.45; text-align: left;">
              <span style="flex-shrink: 0;">⚠️</span>
              <div>${res.message || 'Gagal menyimpan kontak.'} ${isQuota ? '<span class="wasender-pro-badge">PRO</span>' : ''}</div>
            </div>
          `;
          saveStatusMsg.style.display = 'block';
          saveStatusMsg.style.color = '#e11d48';
          this.bindUpgradeLinks();
        }
        alert(res.message || 'Gagal menyimpan kontak.');
      }
    });

    // 2. Download Template CSV (PRO Gated)
    document.getElementById('wasender-btn-download-csv-template')?.addEventListener('click', () => {
      if (!isPro) {
        alert('Fitur Unduh Template Kontak CSV adalah fitur khusus Paket PRO.\n\nSilakan upgrade ke Paket PRO untuk mengunduh contoh dan mengimpor file kontak.');
        return;
      }
      const csvContent = 'nomor,nama\n081234567890,Budi Santoso\n089876543210,Siti Rahma\n6285712345678,Ahmad Fauzi\n';
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.setAttribute('href', url);
      link.setAttribute('download', 'template-kontak-wasender.csv');
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    });

    // 3. Clear Contacts
    document.getElementById('wasender-btn-clear-contacts')?.addEventListener('click', () => {
      if (contactsInput) {
        contactsInput.value = '';
        this.draftContacts = '';
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });

    // 4. CSV Import (PRO Gated)
    const btnImportCsv = document.getElementById('wasender-btn-import-csv');
    const csvFileInput = document.getElementById('wasender-csv-file') as HTMLInputElement | null;

    btnImportCsv?.addEventListener('click', () => {
      if (!isPro) {
        alert('Fitur Impor CSV hanya tersedia untuk Paket PRO. Silakan upgrade akun Anda untuk kemudahan impor kontak massal.');
        return;
      }
      csvFileInput?.click();
    });

    csvFileInput?.addEventListener('change', (e) => {
      const file = (e.target as HTMLInputElement).files?.[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        const text = event.target?.result as string;
        if (text && contactsInput) {
          contactsInput.value = contactsInput.value ? contactsInput.value + '\n' + text : text;
          this.draftContacts = contactsInput.value;
          this.saveDraftsToStorage();
          updatePreviewAndCount();
        }
      };
      reader.readAsText(file);
    });

    // 5. Template Selector in Broadcast
    templateSelect?.addEventListener('change', () => {
      if (templateSelect.value && msgInput) {
        msgInput.value = decodeURIComponent(templateSelect.value);
        this.draftMessage = msgInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });

    // 6. Tag Injectors: + {nama}, + {nomor}, + [acak]
    document.getElementById('wasender-btn-bc-tag-name')?.addEventListener('click', () => {
      if (msgInput) {
        this.insertAtCursor(msgInput, '{nama}');
        this.draftMessage = msgInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });
    document.getElementById('wasender-btn-bc-tag-nomor')?.addEventListener('click', () => {
      if (msgInput) {
        this.insertAtCursor(msgInput, '{nomor}');
        this.draftMessage = msgInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });
    document.getElementById('wasender-btn-bc-tag-spintax')?.addEventListener('click', () => {
      if (!isPro) {
        alert('Variasi kata acak [acak] adalah fitur khusus Paket PRO.');
        return;
      }
      if (msgInput) {
        this.insertAtCursor(msgInput, '{Halo|Hai|Selamat Pagi}');
        this.draftMessage = msgInput.value;
        this.saveDraftsToStorage();
        updatePreviewAndCount();
      }
    });

    const cbRandomCode = document.getElementById('wasender-cb-random-code') as HTMLInputElement | null;
    cbRandomCode?.addEventListener('change', () => {
      this.draftRandomCode = cbRandomCode.checked;
      this.saveDraftsToStorage();
    });

    // 7. Delay Preset Change
    const delayPresetSelect = document.getElementById('wasender-select-delay-preset') as HTMLSelectElement | null;
    const customDelayRow = document.getElementById('wasender-custom-delay-row');
    const minDelayInput = document.getElementById('wasender-min-delay') as HTMLInputElement | null;
    const maxDelayInput = document.getElementById('wasender-max-delay') as HTMLInputElement | null;

    delayPresetSelect?.addEventListener('change', () => {
      this.draftDelayPreset = delayPresetSelect.value;
      if (customDelayRow) {
        customDelayRow.style.display = delayPresetSelect.value === 'custom' ? 'flex' : 'none';
      }
      this.saveDraftsToStorage();
    });

    minDelayInput?.addEventListener('input', () => {
      this.draftMinDelay = Number(minDelayInput.value) || 5;
      this.saveDraftsToStorage();
    });

    maxDelayInput?.addEventListener('input', () => {
      this.draftMaxDelay = Number(maxDelayInput.value) || 10;
      this.saveDraftsToStorage();
    });

    // 8. Attachments Controls (Free Locked, Pro Max 10)
    const btnAttachmentLocked = document.getElementById('wasender-btn-attachment-locked');
    const btnAttachmentFreeNotice = document.getElementById('wasender-btn-attachment-free-notice');
    const onFreeAttachmentClick = () => {
      alert('Fitur kirim gambar & file hanya tersedia untuk pengguna paket PRO (hingga 10 file per broadcast).\n\nSilakan upgrade paket Anda untuk menggunakan fitur ini.');
    };
    btnAttachmentLocked?.addEventListener('click', onFreeAttachmentClick);
    btnAttachmentFreeNotice?.addEventListener('click', onFreeAttachmentClick);

    const filePicker = document.getElementById('wasender-file-picker') as HTMLInputElement | null;
    const btnAddAttachment = document.getElementById('wasender-btn-add-attachment');
    const btnClearAttachments = document.getElementById('wasender-btn-clear-attachments');
    const attachmentsList = document.getElementById('wasender-attachments-list');
    const attachmentCountLabel = document.getElementById('wasender-attachment-count-label');

    const updateAttachmentsUi = () => {
      if (attachmentCountLabel) {
        attachmentCountLabel.textContent = `${this.selectedAttachments.length} / 10 file`;
      }
      if (btnClearAttachments) {
        btnClearAttachments.style.display = this.selectedAttachments.length > 0 ? 'inline-block' : 'none';
      }
      if (attachmentsList) {
        attachmentsList.innerHTML = this.renderAttachmentChipsHtml();
        attachmentsList.querySelectorAll('.wasender-attachment-chip-remove').forEach((btn) => {
          btn.addEventListener('click', (e) => {
            const idx = parseInt((e.currentTarget as HTMLElement).getAttribute('data-idx') || '-1', 10);
            if (idx >= 0 && idx < this.selectedAttachments.length) {
              this.selectedAttachments.splice(idx, 1);
              updateAttachmentsUi();
            }
          });
        });
      }
    };

    btnAddAttachment?.addEventListener('click', () => {
      if (this.selectedAttachments.length >= 10) {
        alert('Maksimal lampiran yang dapat dikirim adalah 10 file per broadcast.');
        return;
      }
      filePicker?.click();
    });

    filePicker?.addEventListener('change', async () => {
      const files = Array.from(filePicker.files || []);
      if (files.length === 0) return;

      const remainingSlots = 10 - this.selectedAttachments.length;
      if (remainingSlots <= 0) {
        alert('Batas maksimal 10 file lampiran telah tercapai.');
        filePicker.value = '';
        return;
      }

      let toAdd = files;
      if (files.length > remainingSlots) {
        alert(`Maksimal 10 lampiran untuk paket PRO. Hanya ${remainingSlots} file pertama yang ditambahkan.`);
        toAdd = files.slice(0, remainingSlots);
      }

      for (const file of toAdd) {
        try {
          const dataUrl = await new Promise<string>((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result as string);
            reader.onerror = reject;
            reader.readAsDataURL(file);
          });
          this.selectedAttachments.push({
            name: file.name,
            type: file.type || 'application/octet-stream',
            dataUrl,
            size: file.size
          });
        } catch (err) {
          console.warn('Gagal membaca file lampiran:', file.name, err);
        }
      }

      filePicker.value = '';
      updateAttachmentsUi();
    });

    btnClearAttachments?.addEventListener('click', () => {
      this.selectedAttachments = [];
      updateAttachmentsUi();
    });

    // 9. Broadcast Execution Controls
    const btnStart = document.getElementById('wasender-btn-start') as HTMLButtonElement | null;
    const btnPause = document.getElementById('wasender-btn-pause') as HTMLButtonElement | null;
    const btnResume = document.getElementById('wasender-btn-resume') as HTMLButtonElement | null;
    const btnStop = document.getElementById('wasender-btn-stop') as HTMLButtonElement | null;

    btnStart?.addEventListener('click', async () => {
      const contacts = parseContactList(contactsInput?.value || '');
      if (contacts.length === 0) {
        alert('Silakan masukkan minimal 1 nomor tujuan.');
        return;
      }

      // Check Free Tier contact limit (max 20)
      if (!isPro && contacts.length > 20) {
        alert(`Paket Free dibatasi maksimal 20 kontak per broadcast (Anda memasukkan ${contacts.length} kontak). Silakan kurangi kontak atau upgrade ke Pro.`);
        return;
      }

      // Check Free Tier attachments limit (strictly prohibited)
      if (!isPro && this.selectedAttachments.length > 0) {
        alert('Pengiriman file dan gambar tidak tersedia untuk paket Free. Silakan hapus lampiran atau upgrade ke paket PRO.');
        return;
      }

      // Check Pro Tier attachments limit (max 10)
      if (this.selectedAttachments.length > 10) {
        alert(`Maksimal lampiran yang dapat dikirim adalah 10 file per broadcast (Anda memilih ${this.selectedAttachments.length} file).`);
        return;
      }

      const template = msgInput?.value.trim() || '';
      if (!template) {
        alert('Silakan tuliskan template pesan broadcast.');
        return;
      }

      // Server batch validation
      btnStart.disabled = true;
      btnStart.textContent = 'Memvalidasi Kuota...';

      const val = await validateBatchApi(contacts.length, this.selectedAttachments.length);
      btnStart.disabled = false;
      btnStart.textContent = 'Mulai Kirim';

      if (!val.allowed) {
        alert(val.message || 'Validasi pengiriman ditolak oleh server.');
        return;
      }

      // Determine Delay from Preset
      let minDelay = 5;
      let maxDelay = 10;
      if (isPro) {
        const preset = delayPresetSelect?.value || 'safe';
        if (preset === 'relaxed') { minDelay = 10; maxDelay = 20; }
        else if (preset === 'medium') { minDelay = 3; maxDelay = 5; }
        else if (preset === 'fast') { minDelay = 1; maxDelay = 3; }
        else if (preset === 'custom') {
          minDelay = parseInt((document.getElementById('wasender-min-delay') as HTMLInputElement)?.value || '5', 10);
          maxDelay = parseInt((document.getElementById('wasender-max-delay') as HTMLInputElement)?.value || '10', 10);
        }
      }

      const appendRandomCode = (document.getElementById('wasender-cb-random-code') as HTMLInputElement)?.checked;

      const settings: SenderSettings = {
        minDelaySec: minDelay,
        maxDelaySec: maxDelay,
        minTypingSpeedMs: 50,
        maxTypingSpeedMs: 120
      };

      chrome.runtime?.sendMessage({
        type: 'START_QUEUE',
        payload: {
          contacts,
          template,
          settings,
          appendRandomCode,
          attachments: this.selectedAttachments.length > 0 ? this.selectedAttachments : undefined
        }
      });
    });

    btnPause?.addEventListener('click', () => {
      chrome.runtime?.sendMessage({ type: 'PAUSE_QUEUE' });
    });

    btnResume?.addEventListener('click', () => {
      chrome.runtime?.sendMessage({ type: 'RESUME_QUEUE' });
    });

    btnStop?.addEventListener('click', () => {
      chrome.runtime?.sendMessage({ type: 'STOP_QUEUE' });
    });
  }

  // TAB 2: Combined Library (Templates & Contacts) Logic
  private initLibraryTab() {
    const isPro = this.currentAuth?.plan && !this.currentAuth.plan.is_free;

    // --- TEMPLATES LOGIC ---
    const tplSearchInput = document.getElementById('wasender-templates-search') as HTMLInputElement | null;
    const tplContainer = document.getElementById('wasender-templates-container');
    const btnRefreshTpl = document.getElementById('wasender-btn-refresh-templates');

    const filterAndRenderTemplates = () => {
      const q = (tplSearchInput?.value || '').toLowerCase().trim();
      const filtered = this.templatesList.filter((t) =>
        !q || t.title.toLowerCase().includes(q) || t.content.toLowerCase().includes(q) || (t.category && t.category.toLowerCase().includes(q))
      );
      if (tplContainer) {
        tplContainer.innerHTML = this.renderTemplatesListHtml(!!isPro, filtered);
        this.bindTemplateActions();
      }
    };

    tplSearchInput?.addEventListener('input', filterAndRenderTemplates);

    btnRefreshTpl?.addEventListener('click', async () => {
      btnRefreshTpl.textContent = 'Memuat...';
      const res = await getTemplatesApi();
      btnRefreshTpl.textContent = '🔄';
      if (res.success && res.data) {
        this.templatesList = res.data;
        const countBadge = document.getElementById('wasender-templates-count');
        if (countBadge) countBadge.textContent = `${this.templatesList.length} template`;
        filterAndRenderTemplates();
      }
    });

    this.bindTemplateActions();

    // --- CONTACTS LOGIC ---
    const filterGroup = document.getElementById('wasender-contacts-filter-group') as HTMLSelectElement | null;
    const searchContactsInput = document.getElementById('wasender-contacts-search') as HTMLInputElement | null;
    const btnRefreshContacts = document.getElementById('wasender-btn-refresh-contacts');
    const contactsContainer = document.getElementById('wasender-contacts-container');
    const btnBroadcastAll = document.getElementById('wasender-btn-broadcast-all-contacts');

    const getFilteredContacts = () => {
      const selectedGroup = filterGroup?.value || '__all__';
      const query = (searchContactsInput?.value || '').toLowerCase().trim();

      return this.contactsList.filter((c) => {
        const matchesGroup = selectedGroup === '__all__' || (c.group_name || 'Umum') === selectedGroup;
        const matchesQuery = !query || c.name.toLowerCase().includes(query) || c.phone.includes(query);
        return matchesGroup && matchesQuery;
      });
    };

    const updateContactsList = () => {
      if (contactsContainer) {
        const filtered = getFilteredContacts();
        contactsContainer.innerHTML = this.renderContactsListHtml(filtered);
        this.bindSingleContactAdd();
      }
    };

    filterGroup?.addEventListener('change', updateContactsList);
    searchContactsInput?.addEventListener('input', updateContactsList);

    btnRefreshContacts?.addEventListener('click', async () => {
      btnRefreshContacts.textContent = 'Memuat...';
      const res = await getContactsApi();
      btnRefreshContacts.textContent = '🔄';
      if (res.success && res.data) {
        this.contactsList = res.data;
        const countBadge = document.getElementById('wasender-contacts-count');
        if (countBadge) countBadge.textContent = `${this.contactsList.length} kontak`;
        updateContactsList();
      }
    });

    btnBroadcastAll?.addEventListener('click', () => {
      const filtered = getFilteredContacts();
      if (filtered.length === 0) {
        alert('Tidak ada kontak untuk dimasukkan ke broadcast.');
        return;
      }
      const isPro = this.currentAuth?.plan && !this.currentAuth.plan.is_free;
      const toInsert = !isPro ? filtered.slice(0, 1) : filtered;
      if (!isPro && filtered.length > 1) {
        alert(`Paket Free dibatasi hanya 1 kontak dari buku kontak. Upgrade ke Pro untuk memasukkan seluruh kontak tanpa batas.`);
      }
      const lines = toInsert.map((c) => `${c.phone}, ${c.name}`).join('\n');
      this.draftContacts = this.draftContacts ? this.draftContacts + '\n' + lines : lines;
      this.saveDraftsToStorage();
      this.activeTab = 'broadcast';
      this.render();
    });

    this.bindSingleContactAdd();

    // Manage via Web Dashboard
    document.getElementById('wasender-btn-manage-web')?.addEventListener('click', () => {
      this.openWebPage('/wasender/contacts');
    });
  }

  private bindTemplateActions() {
    this.rootEl?.querySelectorAll('.wasender-btn-use-broadcast').forEach((btn) => {
      btn.addEventListener('click', () => {
        const content = decodeURIComponent(btn.getAttribute('data-content') || '');
        this.draftMessage = content;
        this.saveDraftsToStorage();
        this.activeTab = 'broadcast';
        this.render();
      });
    });

    this.rootEl?.querySelectorAll('.wasender-btn-copy-template').forEach((btn) => {
      btn.addEventListener('click', () => {
        const content = decodeURIComponent(btn.getAttribute('data-content') || '');
        navigator.clipboard.writeText(content).then(() => {
          btn.textContent = 'Tersalin!';
          setTimeout(() => { btn.textContent = '📋 Salin'; }, 1500);
        });
      });
    });
  }

  private bindSingleContactAdd() {
    this.rootEl?.querySelectorAll('.wasender-btn-add-single-contact').forEach((btn) => {
      btn.addEventListener('click', () => {
        const line = btn.getAttribute('data-line') || '';
        this.draftContacts = this.draftContacts ? this.draftContacts + '\n' + line : line;
        this.saveDraftsToStorage();
        this.activeTab = 'broadcast';
        this.render();
      });
    });
  }

  // TAB 3: Tools & Privacy Logic
  private initToolsTab() {
    const cbLastMsg = document.getElementById('wasender-blur-lastmsg') as HTMLInputElement | null;
    const cbNames = document.getElementById('wasender-blur-names') as HTMLInputElement | null;
    const cbAvatars = document.getElementById('wasender-blur-avatars') as HTMLInputElement | null;
    const cbMedia = document.getElementById('wasender-blur-media') as HTMLInputElement | null;

    const onPrivacyChange = () => {
      this.savePrivacyBlurs({
        lastMsg: !!cbLastMsg?.checked,
        names: !!cbNames?.checked,
        avatars: !!cbAvatars?.checked,
        media: !!cbMedia?.checked
      });
    };

    cbLastMsg?.addEventListener('change', onPrivacyChange);
    cbNames?.addEventListener('change', onPrivacyChange);
    cbAvatars?.addEventListener('change', onPrivacyChange);
    cbMedia?.addEventListener('change', onPrivacyChange);
  }

  // Extract contact numbers from active open WhatsApp group
  public scrapeActiveGroup(): Array<{ phone: string; name: string }> {
    const results: Array<{ phone: string; name: string }> = [];
    const seenPhones = new Set<string>();

    const normalizeAndAdd = (phoneRaw: string, nameRaw?: string) => {
      let clean = phoneRaw.replace(/[^0-9]/g, '');
      if (clean.startsWith('0')) {
        clean = '62' + clean.substring(1);
      }
      if (clean.length >= 9 && clean.length <= 15 && !seenPhones.has(clean)) {
        seenPhones.add(clean);
        const name = (nameRaw && nameRaw.trim() && nameRaw !== phoneRaw && !nameRaw.startsWith('+'))
          ? nameRaw.trim()
          : `Member ${clean.slice(-4)}`;
        results.push({ phone: clean, name });
      }
    };

    // 1. Group info drawer (if opened on right or left)
    const infoDrawer = document.querySelector('[data-testid="chat-info-drawer"], div[role="region"], section');
    if (infoDrawer) {
      const participantItems = infoDrawer.querySelectorAll('[data-testid="cell-frame-container"], [role="listitem"], div[tabindex="-1"]');
      participantItems.forEach((item) => {
        const titleSpan = item.querySelector('span[title], span[dir="auto"]');
        const text = titleSpan?.getAttribute('title') || titleSpan?.textContent || '';
        const phoneMatches = text.match(/(?:\+?62|08)[0-9\s-]{8,20}/g);
        if (phoneMatches) {
          phoneMatches.forEach((pm) => normalizeAndAdd(pm));
        } else {
          const subSpans = item.querySelectorAll('span');
          subSpans.forEach((s) => {
            const st = s.textContent || '';
            const matches = st.match(/(?:\+?62|08)[0-9\s-]{8,20}/g);
            if (matches) matches.forEach((pm) => normalizeAndAdd(pm, text));
          });
        }
      });
    }

    // 2. Active chat header subtitle (contains participant phone numbers separated by comma)
    const mainHeader = document.querySelector('#main header, header');
    if (mainHeader) {
      const headerSpans = mainHeader.querySelectorAll('span[title], span[dir="auto"], span');
      headerSpans.forEach((span) => {
        const text = span.getAttribute('title') || span.textContent || '';
        const matches = text.match(/(?:\+?62|08)[0-9\s-]{8,20}/g);
        if (matches) {
          matches.forEach((m) => normalizeAndAdd(m));
        }
      });
    }

    // 3. Fallback: message sender labels inside #main
    const messageRows = document.querySelectorAll('#main div[data-testid="msg-container"], #main div[role="row"]');
    messageRows.forEach((row) => {
      const authorSpan = row.querySelector('span[aria-label], span[dir="auto"]');
      const authorText = authorSpan?.textContent || '';
      const phoneMatches = authorText.match(/(?:\+?62|08)[0-9\s-]{8,20}/g);
      if (phoneMatches) {
        phoneMatches.forEach((pm) => normalizeAndAdd(pm));
      }
    });

    return results;
  }

  public getActiveChatTitle(): string {
    const mainHeader = document.querySelector('#main header, header');
    if (mainHeader) {
      const titleEl = mainHeader.querySelector('span[title], div[role="button"] span[title]');
      return titleEl?.getAttribute('title') || titleEl?.textContent?.trim() || '';
    }
    return '';
  }

  private refreshContactBookDropdown() {
    const selectBook = document.getElementById('wasender-select-book') as HTMLSelectElement | null;
    const datalist = document.getElementById('wasender-existing-groups-list');
    if (!selectBook) return;

    const contactGroups = Array.from(new Set(this.contactsList.map((c) => c.group_name || 'Umum')));
    selectBook.innerHTML = `
      <option value="">-- 📖 Pilih Buku Kontak --</option>
      ${this.contactsList.length > 0 ? `
        <option value="__all__">Semua Kontak (${this.contactsList.length})</option>
        ${contactGroups.map((g) => {
          const count = this.contactsList.filter((c) => (c.group_name || 'Umum') === g).length;
          return `<option value="${g}">Grup: ${g} (${count})</option>`;
        }).join('')}
      ` : '<option value="" disabled>Belum ada kontak web</option>'}
    `;

    if (datalist) {
      datalist.innerHTML = contactGroups.map((g) => `<option value="${g}">`).join('');
    }
  }

  private updateQueueProgressUI(state: QueueState | null) {
    if (!state) return;

    const statusEl = document.getElementById('wasender-queue-status');
    const percentEl = document.getElementById('wasender-queue-percentage');
    const progressEl = document.getElementById('wasender-queue-progressbar');
    const cntTotal = document.getElementById('wasender-cnt-total');
    const cntSuccess = document.getElementById('wasender-cnt-success');
    const cntFailed = document.getElementById('wasender-cnt-failed');

    const btnStart = document.getElementById('wasender-btn-start') as HTMLButtonElement | null;
    const btnPause = document.getElementById('wasender-btn-pause') as HTMLButtonElement | null;
    const btnResume = document.getElementById('wasender-btn-resume') as HTMLButtonElement | null;
    const btnStop = document.getElementById('wasender-btn-stop') as HTMLButtonElement | null;

    const total = state.contacts.length;
    const success = state.contacts.filter((c) => c.status === 'SUCCESS').length;
    const failed = state.contacts.filter((c) => c.status.startsWith('FAILED')).length;
    const processed = success + failed;
    const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;

    if (cntTotal) cntTotal.textContent = String(total);
    if (cntSuccess) cntSuccess.textContent = String(success);
    if (cntFailed) cntFailed.textContent = String(failed);
    if (percentEl) percentEl.textContent = `${percentage}%`;
    if (progressEl) progressEl.style.width = `${percentage}%`;

    if (statusEl) {
      if (state.runStatus === 'RUNNING') {
        if (state.isCooldown) {
          statusEl.textContent = `☕ Istirahat (${state.cooldownRemainingSec || 35}s)...`;
          statusEl.style.color = '#d97706';
        } else {
          statusEl.textContent = `Mengirim ${state.currentIndex + 1}/${total}...`;
          statusEl.style.color = '#059669';
        }
      } else if (state.runStatus === 'PAUSED') {
        statusEl.textContent = 'Dijeda';
        statusEl.style.color = '#d97706';
      } else if (state.runStatus === 'COMPLETED') {
        statusEl.textContent = 'Selesai';
        statusEl.style.color = '#059669';
      } else {
        statusEl.textContent = 'Siap Mengirim';
        statusEl.style.color = '#0f172a';
      }
    }

    // Sync the Screen Lock Shield on WhatsApp Web
    this.updateScreenLockShield(state);

    if (btnStart && btnPause && btnResume && btnStop) {
      if (state.runStatus === 'RUNNING') {
        btnStart.style.display = 'none';
        btnPause.style.display = 'inline-flex';
        btnResume.style.display = 'none';
        btnStop.disabled = false;
      } else if (state.runStatus === 'PAUSED') {
        btnStart.style.display = 'none';
        btnPause.style.display = 'none';
        btnResume.style.display = 'inline-flex';
        btnStop.disabled = false;
      } else {
        btnStart.style.display = 'inline-flex';
        btnPause.style.display = 'none';
        btnResume.style.display = 'none';
        btnStop.disabled = true;
      }
    }
  }

  private syncQueueState() {
    if (!isExtensionContextValid() || !chrome?.runtime?.sendMessage) {
      return;
    }
    try {
      chrome.runtime.sendMessage({ type: 'GET_QUEUE_STATE' }, (res) => {
        try {
          if (!isExtensionContextValid() || chrome.runtime?.lastError) {
            return;
          }
          if (res && res.state) {
            this.updateQueueProgressUI(res.state);
          }
        } catch {
          // ignore
        }
      });
    } catch {
      // ignore
    }
  }

  private stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  }

  private updateScreenLockShield(state: QueueState | null) {
    let shield = document.getElementById('wasender-screen-shield');

    if (!state || state.runStatus !== 'RUNNING') {
      if (shield) {
        shield.remove();
      }
      return;
    }

    if (!shield) {
      shield = document.createElement('div');
      shield.id = 'wasender-screen-shield';
      document.body.appendChild(shield);
    }

    const total = state.contacts.length;
    const current = Math.min(state.currentIndex + 1, total);

    if (state.isCooldown) {
      shield.innerHTML = `
        <div class="wasender-shield-card">
          <div class="wasender-shield-icon">☕</div>
          <div class="wasender-shield-title">Jeda Istirahat Akun (${state.cooldownRemainingSec || 35}s)</div>
          <div class="wasender-shield-desc">
            Sistem mengambil jeda istirahat berkala untuk menjaga reputasi nomor WhatsApp Anda agar aman dari deteksi robot.
          </div>
          <div class="wasender-shield-tip">
            ✨ Progres: ${current} dari ${total} kontak. Pengiriman akan otomatis dilanjutkan.
          </div>
        </div>
      `;
    } else {
      shield.innerHTML = `
        <div class="wasender-shield-card">
          <div class="wasender-shield-icon">🔒</div>
          <div class="wasender-shield-title">Broadcast Sedang Berjalan (${current} / ${total})</div>
          <div class="wasender-shield-desc">
            Tampilan WhatsApp dikunci sementara agar pengiriman akurat dan tidak terinterupsi salah klik.
          </div>
          <div class="wasender-shield-tip">
            👉 Kontrol antrean (Jeda / Hentikan) tetap aktif di panel sidebar Sirkelta WaSender sebelah kanan.
          </div>
        </div>
      `;
    }
  }

  private renderAttachmentChipsHtml(): string {
    if (this.selectedAttachments.length === 0) {
      return '<span style="color: #94a3b8; font-style: italic; font-size: 10.5px;">Belum ada file terpilih.</span>';
    }
    return this.selectedAttachments
      .map((att, idx) => {
        const isImg = att.type.startsWith('image/');
        const icon = isImg ? '🖼️' : '📄';
        const sizeKb = att.size ? Math.round(att.size / 1024) + ' KB' : '';
        return `
        <div class="wasender-attachment-chip" data-idx="${idx}">
          <span>${icon}</span>
          <span class="wasender-attachment-chip-name" title="${att.name}">${att.name}</span>
          ${sizeKb ? `<span class="wasender-attachment-chip-size">(${sizeKb})</span>` : ''}
          <button type="button" class="wasender-attachment-chip-remove" data-idx="${idx}" title="Hapus file">×</button>
        </div>
      `;
      })
      .join('');
  }

  public destroy() {
    this.stopPolling();
    this.toggleSidebar(false);
    document.getElementById('wasender-screen-shield')?.remove();
    this.rootEl?.remove();
    this.rootEl = null;
  }

  private insertAtCursor(textarea: HTMLTextAreaElement, text: string) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const val = textarea.value;
    textarea.value = val.substring(0, start) + text + val.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + text.length;
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  }
}
