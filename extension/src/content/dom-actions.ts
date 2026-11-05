import { randomBetween, sleep } from '../shared/sleeper';

export const CHAT_INPUT_SELECTOR =
  'footer [role="textbox"], footer [contenteditable="true"], footer [contenteditable]';

export async function waitForElement(selector: string, timeoutMs: number = 25000): Promise<HTMLElement | null> {
  const startTime = Date.now();
  return new Promise((resolve) => {
    const existing = document.querySelector<HTMLElement>(selector);
    if (existing) {
      resolve(existing);
      return;
    }

    const interval = setInterval(() => {
      const el = document.querySelector<HTMLElement>(selector);
      if (el) {
        clearInterval(interval);
        resolve(el);
      } else if (Date.now() - startTime > timeoutMs) {
        clearInterval(interval);
        resolve(null);
      }
    }, 400);
  });
}

export function detectInvalidNumberDialog(): HTMLElement | null {
  // WhatsApp invalid phone number modal dialog
  const dialog = document.querySelector('div[role="dialog"]');
  if (dialog) {
    const text = dialog.textContent || '';
    const lower = text.toLowerCase();
    const hasInvalid = lower.includes('tidak valid') || lower.includes('invalid');
    const hasContext =
      lower.includes('nomor') ||
      lower.includes('phone') ||
      lower.includes('number') ||
      lower.includes('url') ||
      lower.includes('tautan');

    if (hasInvalid && hasContext) {
      const okButton = dialog.querySelector<HTMLElement>('button');
      return okButton || null;
    }
  }
  return null;
}

export function detectWhatsAppRateLimitDialog(): { isRateLimited: boolean; message: string } | null {
  const dialog = document.querySelector('div[role="dialog"]');
  if (dialog) {
    const text = dialog.textContent || '';
    const lower = text.toLowerCase();
    if (
      lower.includes('terlalu banyak') ||
      lower.includes('too many') ||
      lower.includes('aktivitas tidak wajar') ||
      lower.includes('unusual activity') ||
      lower.includes('tindakan diblokir') ||
      lower.includes('action blocked') ||
      lower.includes('coba lagi nanti') ||
      lower.includes('try again later') ||
      lower.includes('spam')
    ) {
      return { isRateLimited: true, message: text.slice(0, 150) };
    }
  }
  return null;
}

export async function simulateHumanTyping(
  element: HTMLElement,
  text: string,
  minSpeedMs: number = 50,
  maxSpeedMs: number = 120
): Promise<void> {
  element.focus();

  for (let i = 0; i < text.length; i++) {
    const char = text[i];

    // Method 1: execCommand insertText (most reliable for rich-text / Lexical in WhatsApp)
    const success = document.execCommand('insertText', false, char);

    if (!success) {
      // Fallback: Dispatch synthetic InputEvent
      const inputEvent = new InputEvent('beforeinput', {
        bubbles: true,
        cancelable: true,
        data: char,
        inputType: 'insertText'
      });
      element.dispatchEvent(inputEvent);
    }

    let delay = randomBetween(minSpeedMs, maxSpeedMs);
    // Add natural human typing cadence for spaces, punctuation, and newlines
    if (char === ' ' || char === ',' || char === '.' || char === '\n' || char === '!' || char === '?') {
      delay += randomBetween(60, 140);
    }
    await sleep(delay);
  }

  // Trigger input change event
  element.dispatchEvent(new Event('input', { bubbles: true }));
}

export async function clickSendButton(): Promise<boolean> {
  // Selector options for WhatsApp Web send button
  const sendIcon =
    document.querySelector('span[data-icon="send"]') ||
    document.querySelector('span[data-icon="send-light"]');

  if (sendIcon) {
    const button = sendIcon.closest('button');
    if (button) {
      button.click();
      return true;
    }
  }

  // Fallback: Press Enter key in active element
  const activeEl = document.activeElement;
  if (activeEl) {
    const enterEvent = new KeyboardEvent('keydown', {
      key: 'Enter',
      code: 'Enter',
      keyCode: 13,
      which: 13,
      bubbles: true,
      cancelable: true
    });
    activeEl.dispatchEvent(enterEvent);
    return true;
  }

  return false;
}

export function isChatOpenFor(contact: { phone: string; name?: string }): boolean {
  const mainHeader = document.querySelector('#main header');
  if (!mainHeader) return false;

  const rawText = mainHeader.textContent || '';
  const digitsOnly = rawText.replace(/[^0-9]/g, '');
  const cleanPhone = contact.phone.replace(/[^0-9]/g, '');

  if (cleanPhone && digitsOnly.includes(cleanPhone)) return true;
  if (contact.name && contact.name.trim() && rawText.toLowerCase().includes(contact.name.trim().toLowerCase())) {
    return true;
  }
  return false;
}

export async function clearSearch(): Promise<void> {
  const cancelBtn =
    document.querySelector<HTMLElement>('#side [data-icon="back"]')?.closest('button, [role="button"]') ||
    document.querySelector<HTMLElement>('#side [data-icon="arrow-left"]')?.closest('button, [role="button"]') ||
    document.querySelector<HTMLElement>('#side span[data-icon="x-alt"]')?.closest('button, [role="button"]') ||
    document.querySelector<HTMLElement>('span[data-icon="x-alt"]')?.closest('button, [role="button"]') ||
    document.querySelector<HTMLElement>('span[data-icon="back"]')?.closest('button, [role="button"]') ||
    document.querySelector<HTMLElement>('button[aria-label*="Batalkan" i]') ||
    document.querySelector<HTMLElement>('button[aria-label*="Cancel" i]') ||
    document.querySelector<HTMLElement>('button[title*="Batalkan" i]') ||
    document.querySelector<HTMLElement>('button[title*="Cancel" i]');

  if (cancelBtn) {
    cancelBtn.click();
    await sleep(200);
    return;
  }

  const searchInput =
    document.querySelector<HTMLElement>('#side div[contenteditable="true"]') ||
    document.querySelector<HTMLElement>('div[contenteditable="true"][data-tab="3"]');

  if (searchInput) {
    searchInput.focus();
    document.execCommand('selectAll', false);
    document.execCommand('delete', false);
    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    await sleep(150);
  }
}

export function normalizePhoneForSearch(phone: string): { searchPhone: string; digits: string } {
  const digits = phone.replace(/[^0-9]/g, '');
  let searchPhone = phone.trim();
  if (digits.startsWith('0')) {
    searchPhone = '+62' + digits.slice(1);
  } else if (!searchPhone.startsWith('+')) {
    searchPhone = '+' + digits;
  }
  return { searchPhone, digits };
}

export function findNewChatButton(): HTMLElement | null {
  // 1. Check for data-icon elements (+ / plus, new-chat-outline, chat, etc.)
  const icons = [
    'plus',
    'plus-large',
    'plus-refreshed',
    'new-chat-outline',
    'new-chat',
    'chat',
    'chat-refreshed'
  ];

  for (const name of icons) {
    const iconEls = document.querySelectorAll<HTMLElement>(
      `#side span[data-icon="${name}"], header span[data-icon="${name}"], nav span[data-icon="${name}"], [data-testid="chatlist-header"] span[data-icon="${name}"]`
    );
    for (const iconEl of Array.from(iconEls)) {
      const btn = iconEl.closest<HTMLElement>('button, [role="button"]');
      if (btn) return btn;
    }
  }

  // 2. Check for aria-label or title attributes (Indonesian & English, case-insensitive)
  const selectors = [
    '#side [aria-label*="Chat baru" i]',
    '#side [aria-label*="New chat" i]',
    '#side [title*="Chat baru" i]',
    '#side [title*="New chat" i]',
    'header [aria-label*="Chat baru" i]',
    'header [aria-label*="New chat" i]',
    'header [title*="Chat baru" i]',
    'header [title*="New chat" i]',
    'nav [aria-label*="Chat baru" i]',
    'nav [aria-label*="New chat" i]',
    '[data-testid="chatlist-header"] [aria-label*="Chat baru" i]',
    '[data-testid="chatlist-header"] [aria-label*="New chat" i]'
  ];

  for (const sel of selectors) {
    const el = document.querySelector<HTMLElement>(sel);
    if (el) {
      return el.closest<HTMLElement>('button, [role="button"]') || el;
    }
  }

  // 3. Fallback: Any element with data-icon="plus" in side or header
  const anyPlus = document.querySelector<HTMLElement>(
    '#side span[data-icon*="plus"], header span[data-icon*="plus"], nav span[data-icon*="plus"]'
  );
  if (anyPlus) {
    return anyPlus.closest<HTMLElement>('button, [role="button"]') || anyPlus;
  }

  return null;
}

export function isNewChatDrawerOpen(): boolean {
  // Check back button in #side
  const backBtn = document.querySelector(
    '#side [data-icon="back"], #side [data-icon="arrow-left"], #side [aria-label*="Kembali" i], #side [aria-label*="Back" i]'
  );
  if (backBtn) return true;

  // Check drawer title
  const sideHeaders = document.querySelectorAll('#side header, #side h1, #side h2, #side [data-testid="drawer-header"]');
  for (const h of Array.from(sideHeaders)) {
    const text = (h.textContent || '').toLowerCase();
    if (text.includes('chat baru') || text.includes('new chat')) {
      return true;
    }
  }

  return false;
}

export async function openChatBySearch(phone: string): Promise<boolean> {
  const { searchPhone, digits } = normalizePhoneForSearch(phone);

  // 1. Always open New Chat (+) drawer for new numbers if drawer is not already open
  if (!isNewChatDrawerOpen()) {
    const newChatBtn = findNewChatButton();
    if (newChatBtn) {
      newChatBtn.click();
      newChatBtn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
      newChatBtn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true }));

      // Wait for the drawer to open
      let waited = 0;
      while (waited < 1500 && !isNewChatDrawerOpen()) {
        await sleep(150);
        waited += 150;
      }
      await sleep(200);
    }
  }

  // 2. Locate search input field
  const findSearchInput = (): HTMLElement | null => {
    const selectors = [
      '#side [role="textbox"][contenteditable="true"]',
      '#side div[contenteditable="true"]',
      '#side [role="textbox"]',
      '#side input[type="text"]',
      '#side input',
      'div[data-testid="chat-list-search"] [contenteditable="true"]',
      'div[data-tab="3"][contenteditable="true"]',
      '[role="textbox"][contenteditable="true"]',
      '[contenteditable="true"]'
    ];
    for (const sel of selectors) {
      const els = document.querySelectorAll<HTMLElement>(sel);
      for (const el of Array.from(els)) {
        if (!el.closest('footer')) {
          return el;
        }
      }
    }
    return null;
  };

  let searchInput = findSearchInput();
  if (!searchInput) {
    let waited = 0;
    while (waited < 2000 && !searchInput) {
      await sleep(200);
      waited += 200;
      searchInput = findSearchInput();
    }
  }

  if (searchInput) {
    searchInput.focus();
    await sleep(150);

    // Clear existing search
    if (searchInput instanceof HTMLInputElement) {
      searchInput.value = '';
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    } else {
      document.execCommand('selectAll', false);
      document.execCommand('delete', false);
      searchInput.textContent = '';
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
    await sleep(150);

    // Type phone number into search box (prefer international format with +)
    if (searchInput instanceof HTMLInputElement) {
      searchInput.value = searchPhone;
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
      searchInput.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      document.execCommand('insertText', false, searchPhone);
      searchInput.dispatchEvent(new Event('input', { bubbles: true }));
      // Fallback if execCommand did not set content
      if (!searchInput.textContent?.includes(digits.slice(-4))) {
        searchInput.textContent = searchPhone;
        searchInput.dispatchEvent(
          new InputEvent('beforeinput', { bubbles: true, cancelable: true, data: searchPhone, inputType: 'insertText' })
        );
        searchInput.dispatchEvent(
          new InputEvent('input', { bubbles: true, cancelable: true, data: searchPhone, inputType: 'insertText' })
        );
      }
    }

    // Trigger Enter key
    searchInput.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true })
    );

    // 3. Wait for search results or definitive not found state
    const startTime = Date.now();
    while (Date.now() - startTime < 8000) {
      await sleep(400);

      // Check if invalid dialog popped up
      if (detectInvalidNumberDialog()) {
        return false;
      }

      // Check ONLY for definitive not-registered WhatsApp error text
      const sideText = (document.querySelector('#side')?.textContent || '').toLowerCase();
      if (
        sideText.includes('nomor telepon tidak ada di whatsapp') ||
        sideText.includes('phone number is not on whatsapp') ||
        sideText.includes('tidak terdaftar di whatsapp') ||
        sideText.includes('not registered on whatsapp')
      ) {
        await clearSearch();
        return false;
      }

      // Look for search result items in side list (including "Bukan di kontak" for new numbers)
      const resultItems = Array.from(
        document.querySelectorAll<HTMLElement>(
          '#side div[data-testid="cell-frame-container"], ' +
          '#side div[data-testid="contact-list-key"], ' +
          '#side div[role="listitem"], ' +
          '#side div[data-testid*="list-item"], ' +
          '#side div[data-testid="chat-list-item"]'
        )
      ).filter((item) => !item.closest('header') && !item.querySelector('[role="textbox"]'));

      let targetCell: HTMLElement | null = null;
      if (resultItems.length > 0) {
        const lastDigits = digits.length >= 6 ? digits.slice(-6) : digits;
        targetCell =
          resultItems.find((item) => {
            const itemDigits = (item.textContent || '').replace(/[^0-9]/g, '');
            return itemDigits.includes(lastDigits);
          }) ||
          resultItems.find((item) => {
            const pText = (item.parentElement?.textContent || item.textContent || '').toLowerCase();
            return (
              pText.includes('bukan di kontak') ||
              pText.includes('not in contacts') ||
              pText.includes('chat dengan') ||
              pText.includes('chat with')
            );
          }) ||
          resultItems[0];
      }

      if (targetCell) {
        targetCell.click();
        targetCell.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
        targetCell.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true }));
        searchInput.dispatchEvent(
          new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, which: 13, bubbles: true })
        );
        await sleep(500);

        // Verify chat panel or input is ready
        const chatPanel = document.querySelector('#main');
        const input = document.querySelector<HTMLElement>(CHAT_INPUT_SELECTOR);
        if (chatPanel || input) {
          return true;
        }
      }
    }
  }

  // 4. Direct link navigation fallback if UI search did not find chat
  const cleanDigits = digits.startsWith('0') ? '62' + digits.slice(1) : digits;
  if (cleanDigits.length >= 8) {
    const navLink = document.createElement('a');
    navLink.href = `https://web.whatsapp.com/send?phone=${cleanDigits}`;
    navLink.style.display = 'none';
    document.body.appendChild(navLink);
    navLink.click();
    navLink.remove();

    const navStart = Date.now();
    while (Date.now() - navStart < 5000) {
      await sleep(500);
      if (detectInvalidNumberDialog()) {
        return false;
      }
      const chatPanel = document.querySelector('#main');
      const chatInput = document.querySelector<HTMLElement>(CHAT_INPUT_SELECTOR);
      if (chatPanel || chatInput) {
        return true;
      }
    }
  }

  await clearSearch();
  return false;
}

export function dataUrlToFile(dataUrl: string, fileName: string, mimeType?: string): File {
  const arr = dataUrl.split(',');
  const mime = mimeType || (arr[0].match(/:(.*?);/) || [])[1] || 'image/png';
  const bstr = atob(arr[arr.length - 1]);
  let n = bstr.length;
  const u8arr = new Uint8Array(n);
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n);
  }
  return new File([u8arr], fileName, { type: mime });
}

export async function attachFilesAndSend(
  files: File[],
  caption: string,
  settings?: { minTypingSpeedMs?: number; maxTypingSpeedMs?: number }
): Promise<boolean> {
  if (!files || files.length === 0) return false;

  // 1. Prepare DataTransfer
  const dt = new DataTransfer();
  for (const file of files) {
    dt.items.add(file);
  }

  // 2. Try pasting to chat input first (standard WhatsApp Web desktop drag/paste)
  const chatInput = document.querySelector<HTMLElement>(CHAT_INPUT_SELECTOR);
  if (chatInput) {
    chatInput.focus();
    const pasteEvent = new ClipboardEvent('paste', {
      bubbles: true,
      cancelable: true,
      clipboardData: dt
    });
    chatInput.dispatchEvent(pasteEvent);
    await sleep(600);
  }

  // 3. Fallback: Trigger through attachment button and file input if preview not yet active
  const isMediaPreviewActive = () =>
    Boolean(
      document.querySelector('div[data-testid="media-caption-input-container"]') ||
      document.querySelector('div[data-tab="10"]') ||
      document.querySelector('span[data-icon="send"]')
    );

  if (!isMediaPreviewActive()) {
    const attachBtn =
      document.querySelector<HTMLElement>('span[data-icon="plus"]')?.closest('button') ||
      document.querySelector<HTMLElement>('span[data-icon="attach-menu-plus"]')?.closest('button') ||
      document.querySelector<HTMLElement>('span[data-icon="clip"]')?.closest('button') ||
      document.querySelector<HTMLElement>('button[title*="Lampirkan"]') ||
      document.querySelector<HTMLElement>('button[title*="Attach"]') ||
      document.querySelector<HTMLElement>('div[title*="Lampirkan"]') ||
      document.querySelector<HTMLElement>('div[title*="Attach"]');

    if (attachBtn) {
      attachBtn.click();
      await sleep(400);
    }

    const isAllImages = files.every((f) => f.type.startsWith('image/'));
    let fileInput = isAllImages
      ? document.querySelector<HTMLInputElement>('input[accept*="image"]')
      : document.querySelector<HTMLInputElement>('input[type="file"]');
    if (!fileInput) {
      fileInput = document.querySelector<HTMLInputElement>('input[type="file"]');
    }

    if (fileInput) {
      fileInput.files = dt.files;
      fileInput.dispatchEvent(new Event('change', { bubbles: true }));
      await sleep(800);
    }
  }

  // 4. Wait for media preview drawer / caption input
  const captionSelector =
    'div[data-testid="media-caption-input-container"] [contenteditable="true"], div[data-tab="10"], div[aria-placeholder*="keterangan"], div[aria-placeholder*="caption"], footer div[contenteditable="true"]';
  const captionInput = await waitForElement(captionSelector, 8000);

  // 5. Type caption if provided
  if (caption && caption.trim()) {
    const targetCaptionEl = captionInput || document.querySelector<HTMLElement>(captionSelector);
    if (targetCaptionEl) {
      await simulateHumanTyping(
        targetCaptionEl,
        caption,
        settings?.minTypingSpeedMs || 50,
        settings?.maxTypingSpeedMs || 120
      );
      await sleep(400);
    }
  }

  // 6. Click send button in media preview
  await sleep(randomBetween(600, 1200));
  const sent = await clickSendButton();
  if (!sent && captionInput) {
    captionInput.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }));
  }

  // Wait for media upload and send to register
  await sleep(randomBetween(1500, 2500));
  return true;
}

