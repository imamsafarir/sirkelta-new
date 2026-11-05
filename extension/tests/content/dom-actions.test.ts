// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  simulateHumanTyping,
  waitForElement,
  detectInvalidNumberDialog,
  clickSendButton
} from '../../src/content/dom-actions';

describe('Content Script DOM Actions', () => {
  let mockDiv: HTMLDivElement;

  beforeEach(() => {
    document.body.innerHTML = '';
    mockDiv = document.createElement('div');
    mockDiv.contentEditable = 'true';
    document.body.appendChild(mockDiv);

    document.execCommand = vi.fn((cmd, _showUI, value) => {
      if (cmd === 'insertText') {
        const target =
          document.activeElement && document.activeElement !== document.body
            ? (document.activeElement as HTMLElement)
            : mockDiv;
        target.textContent = (target.textContent || '') + value;
        return true;
      }
      return false;
    });
  });

  afterEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
  });

  describe('simulateHumanTyping', () => {
    it('types text character by character into contenteditable div', async () => {
      await simulateHumanTyping(mockDiv, 'Halo', 1, 5);
      expect(mockDiv.textContent).toBe('Halo');
      expect(document.execCommand).toHaveBeenCalledTimes(4);
    });

    it('falls back to InputEvent when execCommand returns false', async () => {
      document.execCommand = vi.fn(() => false);
      const inputEventListener = vi.fn();
      mockDiv.addEventListener('beforeinput', inputEventListener);

      await simulateHumanTyping(mockDiv, 'Hi', 1, 5);
      expect(inputEventListener).toHaveBeenCalledTimes(2);
    });
  });

  describe('waitForElement', () => {
    it('resolves immediately if element already exists', async () => {
      const el = document.createElement('div');
      el.id = 'target-el';
      document.body.appendChild(el);

      const found = await waitForElement('#target-el', 1000);
      expect(found).toBe(el);
    });

    it('resolves when element appears asynchronously', async () => {
      setTimeout(() => {
        const el = document.createElement('div');
        el.className = 'async-el';
        document.body.appendChild(el);
      }, 50);

      const found = await waitForElement('.async-el', 1000);
      expect(found).not.toBeNull();
      expect(found?.className).toBe('async-el');
    });

    it('resolves null if element does not appear within timeout', async () => {
      const found = await waitForElement('#non-existent', 100);
      expect(found).toBeNull();
    });
  });

  describe('detectInvalidNumberDialog', () => {
    it('returns null if dialog is not present', () => {
      expect(detectInvalidNumberDialog()).toBeNull();
    });

    it('returns null if dialog text does not indicate invalid number', () => {
      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'Selamat datang';
      document.body.appendChild(dialog);

      expect(detectInvalidNumberDialog()).toBeNull();
    });

    it('returns null if dialog contains context keyword but no invalid indicator', () => {
      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'Buka tautan ini untuk menyalin URL nomor telepon.';
      const okBtn = document.createElement('button');
      dialog.appendChild(okBtn);
      document.body.appendChild(dialog);

      expect(detectInvalidNumberDialog()).toBeNull();
    });

    it('returns OK button if dialog indicates invalid phone number in Indonesian', () => {
      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'Nomor telepon yang dibagikan melalui tautan tidak valid.';
      const okBtn = document.createElement('button');
      okBtn.textContent = 'OKE';
      dialog.appendChild(okBtn);
      document.body.appendChild(dialog);

      const result = detectInvalidNumberDialog();
      expect(result).toBe(okBtn);
    });

    it('returns OK button if dialog indicates invalid phone number in English', () => {
      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'Phone number shared via url is invalid.';
      const okBtn = document.createElement('button');
      okBtn.textContent = 'OK';
      dialog.appendChild(okBtn);
      document.body.appendChild(dialog);

      const result = detectInvalidNumberDialog();
      expect(result).toBe(okBtn);
    });

    it('handles uppercase and mixed-case dialog text correctly', () => {
      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'NOMOR TELEPON TIDAK VALID';
      const okBtn = document.createElement('button');
      okBtn.textContent = 'OK';
      dialog.appendChild(okBtn);
      document.body.appendChild(dialog);

      const result = detectInvalidNumberDialog();
      expect(result).toBe(okBtn);
    });
  });

  describe('clickSendButton', () => {
    it('clicks button containing send icon when available', async () => {
      const btn = document.createElement('button');
      const icon = document.createElement('span');
      icon.setAttribute('data-icon', 'send');
      btn.appendChild(icon);
      document.body.appendChild(btn);

      const clickSpy = vi.spyOn(btn, 'click');
      const clicked = await clickSendButton();

      expect(clicked).toBe(true);
      expect(clickSpy).toHaveBeenCalled();
    });

    it('clicks button containing send-light icon when available', async () => {
      const btn = document.createElement('button');
      const icon = document.createElement('span');
      icon.setAttribute('data-icon', 'send-light');
      btn.appendChild(icon);
      document.body.appendChild(btn);

      const clickSpy = vi.spyOn(btn, 'click');
      const clicked = await clickSendButton();

      expect(clicked).toBe(true);
      expect(clickSpy).toHaveBeenCalled();
    });

    it('falls back to pressing Enter on activeElement when send button is not found', async () => {
      const input = document.createElement('input');
      document.body.appendChild(input);
      input.focus();

      let enterDispatched = false;
      input.addEventListener('keydown', (e: KeyboardEvent) => {
        if (e.key === 'Enter') {
          enterDispatched = true;
        }
      });

      const clicked = await clickSendButton();
      expect(clicked).toBe(true);
      expect(enterDispatched).toBe(true);
    });
  });

  describe('isChatOpenFor', () => {
    it('returns true when #main header contains phone or name', async () => {
      const { isChatOpenFor } = await import('../../src/content/dom-actions');
      const main = document.createElement('div');
      main.id = 'main';
      const header = document.createElement('header');
      header.textContent = 'Budi Santoso (+62 812-3456-7890)';
      main.appendChild(header);
      document.body.appendChild(main);

      expect(isChatOpenFor({ phone: '6281234567890', name: 'Budi' })).toBe(true);
      expect(isChatOpenFor({ phone: '6289999999999', name: 'Other' })).toBe(false);
    });
  });

  describe('openChatBySearch', () => {
    it('finds and clicks search result item to open chat', async () => {
      const { openChatBySearch } = await import('../../src/content/dom-actions');
      const side = document.createElement('div');
      side.id = 'side';
      const searchBox = document.createElement('div');
      searchBox.contentEditable = 'true';
      searchBox.setAttribute('role', 'textbox');
      searchBox.tabIndex = 0;
      side.appendChild(searchBox);

      const result = document.createElement('div');
      result.setAttribute('data-testid', 'cell-frame-container');
      result.addEventListener('click', () => {
        const main = document.createElement('div');
        main.id = 'main';
        document.body.appendChild(main);
      });
      side.appendChild(result);
      document.body.appendChild(side);

      const opened = await openChatBySearch('6281234567890');
      expect(opened).toBe(true);
    });

    it('successfully opens chat for new unsaved number even when side panel contains address book empty text', async () => {
      const { openChatBySearch } = await import('../../src/content/dom-actions');
      const side = document.createElement('div');
      side.id = 'side';

      // WhatsApp Web shows "Tidak ada kontak yang ditemukan di buku alamat" for unsaved numbers
      const noticeDiv = document.createElement('div');
      noticeDiv.textContent = 'Tidak ada kontak yang ditemukan di buku alamat';
      side.appendChild(noticeDiv);

      const searchBox = document.createElement('div');
      searchBox.contentEditable = 'true';
      searchBox.setAttribute('role', 'textbox');
      side.appendChild(searchBox);

      // But underneath it displays the global search result under "Bukan di kontak"
      const result = document.createElement('div');
      result.setAttribute('data-testid', 'cell-frame-container');
      result.textContent = 'Chat dengan +62 899-1234-5678';
      result.addEventListener('click', () => {
        const main = document.createElement('div');
        main.id = 'main';
        document.body.appendChild(main);
      });
      side.appendChild(result);
      document.body.appendChild(side);

      const opened = await openChatBySearch('089912345678');
      expect(opened).toBe(true);
    });

    it('clicks the + (plus) new chat button when drawer is not already open', async () => {
      const { openChatBySearch } = await import('../../src/content/dom-actions');

      const side = document.createElement('div');
      side.id = 'side';

      // New Chat '+' button inside #side header
      const plusBtn = document.createElement('div');
      plusBtn.setAttribute('role', 'button');
      plusBtn.setAttribute('aria-label', 'Chat baru');
      const plusIcon = document.createElement('span');
      plusIcon.setAttribute('data-icon', 'plus');
      plusBtn.appendChild(plusIcon);

      let plusClicked = false;
      plusBtn.addEventListener('click', () => {
        plusClicked = true;
        // Drawer opens with back icon and searchbox
        const backIcon = document.createElement('span');
        backIcon.setAttribute('data-icon', 'back');
        side.appendChild(backIcon);

        const searchBox = document.createElement('div');
        searchBox.contentEditable = 'true';
        searchBox.setAttribute('role', 'textbox');
        side.appendChild(searchBox);

        const result = document.createElement('div');
        result.setAttribute('data-testid', 'cell-frame-container');
        result.textContent = 'Chat dengan +62 811-2233-4455';
        result.addEventListener('click', () => {
          const main = document.createElement('div');
          main.id = 'main';
          document.body.appendChild(main);
        });
        side.appendChild(result);
      });

      side.appendChild(plusBtn);
      document.body.appendChild(side);

      const opened = await openChatBySearch('081122334455');
      expect(plusClicked).toBe(true);
      expect(opened).toBe(true);
    });
  });

  describe('normalizePhoneForSearch', () => {
    it('normalizes Indonesian 08 numbers to +62 international format', async () => {
      const { normalizePhoneForSearch } = await import('../../src/content/dom-actions');
      const res1 = normalizePhoneForSearch('081234567890');
      expect(res1.searchPhone).toBe('+6281234567890');
      expect(res1.digits).toBe('081234567890');

      const res2 = normalizePhoneForSearch('6281234567890');
      expect(res2.searchPhone).toBe('+6281234567890');

      const res3 = normalizePhoneForSearch('+6281234567890');
      expect(res3.searchPhone).toBe('+6281234567890');
    });
  });

  describe('dataUrlToFile', () => {
    it('converts base64 data url into a valid File instance', async () => {
      const { dataUrlToFile } = await import('../../src/content/dom-actions');
      // 1x1 transparent PNG data url
      const dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
      const file = dataUrlToFile(dataUrl, 'test.png', 'image/png');

      expect(file).toBeInstanceOf(File);
      expect(file.name).toBe('test.png');
      expect(file.type).toBe('image/png');
      expect(file.size).toBeGreaterThan(0);
    });
  });

  describe('detectWhatsAppRateLimitDialog', () => {
    it('detects rate limit or unusual activity dialog modal', async () => {
      const { detectWhatsAppRateLimitDialog } = await import('../../src/content/dom-actions');

      expect(detectWhatsAppRateLimitDialog()).toBeNull();

      const dialog = document.createElement('div');
      dialog.setAttribute('role', 'dialog');
      dialog.textContent = 'Aktivitas tidak wajar terdeteksi. Silakan coba lagi nanti.';
      document.body.appendChild(dialog);

      const result = detectWhatsAppRateLimitDialog();
      expect(result).not.toBeNull();
      expect(result?.isRateLimited).toBe(true);
    });
  });
});
