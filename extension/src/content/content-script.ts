import {
  waitForElement,
  detectInvalidNumberDialog,
  detectWhatsAppRateLimitDialog,
  simulateHumanTyping,
  clickSendButton,
  openChatBySearch,
  attachFilesAndSend,
  dataUrlToFile,
  isChatOpenFor,
  CHAT_INPUT_SELECTOR
} from './dom-actions';
import { renderTemplate } from '../shared/template';
import { sleep, randomBetween } from '../shared/sleeper';
import { QueueState, IPCMessage, ContactItem, SenderSettings, AttachmentItem } from '../shared/types';
import { isExtensionContextValid } from '../shared/api';
import { WhatsAppSidebar } from './sidebar';

export { CHAT_INPUT_SELECTOR };

let isProcessing = false;
export const sidebarInstance = new WhatsAppSidebar();

// Initialize sidebar when DOM is interactive and chrome runtime is available
if (typeof document !== 'undefined') {
  if (typeof chrome !== 'undefined' && chrome.runtime?.getURL) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => sidebarInstance.init());
    } else {
      sidebarInstance.init();
    }
  }
}

export function getQueueState(): Promise<QueueState | null> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.runtime?.sendMessage) {
      resolve(null);
      return;
    }
    try {
      chrome.runtime.sendMessage({ type: 'GET_QUEUE_STATE' }, (response) => {
        try {
          if (!isExtensionContextValid() || chrome.runtime?.lastError) {
            resolve(null);
            return;
          }
          if (response && response.state) {
            resolve(response.state);
          } else {
            resolve(null);
          }
        } catch {
          resolve(null);
        }
      });
    } catch {
      resolve(null);
    }
  });
}

export async function handleNavigateAndSend(payload: {
  contact: ContactItem;
  template: string;
  settings: SenderSettings;
  appendRandomCode?: boolean;
  attachments?: AttachmentItem[];
}): Promise<void> {
  if (isProcessing) return;
  isProcessing = true;

  const { contact, template, settings, appendRandomCode, attachments } = payload;
  const phone = contact.phone;
  const hasAttachments = Boolean(attachments && attachments.length > 0);

  try {
    // 1. Check if chat is already open for this contact
    let opened = isChatOpenFor(contact);

    if (!opened) {
      // 2. Open chat using human-like search
      opened = await openChatBySearch(phone);
    }

    if (!opened) {
      // Check for WhatsApp rate limit / anti-bot dialog first
      const rateLimit = detectWhatsAppRateLimitDialog();
      if (rateLimit) {
        chrome.runtime.sendMessage({
          type: 'CONTENT_RESULT',
          payload: {
            phone,
            status: 'FAILED_TIMEOUT',
            errorMessage: 'Peringatan batas aktivitas WhatsApp terdeteksi. Pengiriman dijeda demi keamanan nomor.'
          }
        });
        chrome.runtime.sendMessage({ type: 'PAUSE_QUEUE' });
        return;
      }

      // Check for invalid number dialog
      const okButton = detectInvalidNumberDialog();
      if (okButton) {
        okButton.click();
        await sleep(400);
      }

      chrome.runtime.sendMessage({
        type: 'CONTENT_RESULT',
        payload: {
          phone,
          status: 'FAILED_INVALID',
          errorMessage: 'Nomor tidak ditemukan di WhatsApp'
        }
      });
      return;
    }

    // Render template message text
    const messageText = renderTemplate(template, contact, 'Kak/Bapak/Ibu', {
      appendRandomCode
    });

    if (hasAttachments && attachments && attachments.length > 0) {
      // Convert attachment payloads back to File instances
      const files = attachments.map((att) =>
        dataUrlToFile(att.dataUrl, att.name, att.type)
      );

      await attachFilesAndSend(files, messageText, settings);
    } else {
      // 3. Wait for chat input
      const inputEl = await waitForElement(CHAT_INPUT_SELECTOR, 10000);
      if (!inputEl) {
        chrome.runtime.sendMessage({
          type: 'CONTENT_RESULT',
          payload: {
            phone,
            status: 'FAILED_TIMEOUT',
            errorMessage: 'Kolom chat tidak siap'
          }
        });
        return;
      }

      // Natural reading delay before typing (human hesitation)
      await sleep(randomBetween(800, 1500));

      // 5. Human-like typing character by character
      await simulateHumanTyping(
        inputEl,
        messageText,
        settings.minTypingSpeedMs,
        settings.maxTypingSpeedMs
      );

      // 6. Review hesitation before clicking send
      await sleep(randomBetween(700, 1300));

      // 7. Click send
      const sent = await clickSendButton();
      if (!sent) {
        inputEl.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }));
      }

      // Wait for message to register in chat bubble
      await sleep(randomBetween(1200, 1800));
    }

    // 8. Report SUCCESS
    const resultPayload: { phone: string; status: ContactStatus; errorMessage?: string; hasAttachment?: boolean } = {
      phone,
      status: 'SUCCESS'
    };
    if (hasAttachments) {
      resultPayload.hasAttachment = true;
    }

    chrome.runtime.sendMessage({
      type: 'CONTENT_RESULT',
      payload: resultPayload
    });
  } catch (err) {
    chrome.runtime.sendMessage({
      type: 'CONTENT_RESULT',
      payload: {
        phone,
        status: 'FAILED_TIMEOUT',
        errorMessage: (err as Error).message || 'Terjadi kesalahan saat mengetik'
      }
    });
  } finally {
    isProcessing = false;
  }
}

export async function checkAndProcessQueue(): Promise<void> {
  if (isProcessing) return;
  isProcessing = true;

  try {
    // Check if we are on a send page: https://web.whatsapp.com/send?phone=...
    const urlParams = new URLSearchParams(window.location.search);
    const currentPhone = urlParams.get('phone');
    if (!currentPhone) return;

    // Request current queue state from background
    const state = await getQueueState();
    if (!state || state.runStatus !== 'RUNNING') return;

    const currentContact = state.contacts[state.currentIndex];
    if (!currentContact || currentContact.phone !== currentPhone) return;

    // Check for invalid phone number dialog popup
    let retries = 0;
    while (retries < 25) {
      const okButton = detectInvalidNumberDialog();
      if (okButton) {
        okButton.click();
        await sleep(500);
        chrome.runtime.sendMessage({
          type: 'CONTENT_RESULT',
          payload: {
            phone: currentPhone,
            status: 'FAILED_INVALID',
            errorMessage: 'Nomor tidak terdaftar di WhatsApp'
          }
        });
        return;
      }

      // Check if chat input is available
      const inputEl = document.querySelector<HTMLElement>(CHAT_INPUT_SELECTOR);
      if (inputEl) {
        // Chat is open and ready!
        const messageText = renderTemplate(state.template, currentContact, 'Kak/Bapak/Ibu', {
          appendRandomCode: state.appendRandomCode
        });

        await sleep(1000); // Wait 1s after chat loads before typing
        await simulateHumanTyping(
          inputEl,
          messageText,
          state.settings.minTypingSpeedMs,
          state.settings.maxTypingSpeedMs
        );

        await sleep(600); // Wait 600ms before hitting send
        await clickSendButton();

        await sleep(1000); // Wait 1s to ensure send dispatch
        chrome.runtime.sendMessage({
          type: 'CONTENT_RESULT',
          payload: { phone: currentPhone, status: 'SUCCESS' }
        });
        return;
      }

      await sleep(1000);
      retries++;
    }

    // If reached here, timed out
    chrome.runtime.sendMessage({
      type: 'CONTENT_RESULT',
      payload: {
        phone: currentPhone,
        status: 'FAILED_TIMEOUT',
        errorMessage: 'Waktu tunggu obrolan habis (timeout)'
      }
    });
  } finally {
    isProcessing = false;
  }
}

// Listen to URL change events on WhatsApp Web without heavy document subtree observer
if (typeof window !== 'undefined' && typeof location !== 'undefined') {
  let lastUrl = location.href;
  const checkUrlChange = () => {
    if (!isExtensionContextValid()) return;
    const url = location.href;
    if (url !== lastUrl) {
      lastUrl = url;
      setTimeout(checkAndProcessQueue, 1500);
    }
  };

  window.addEventListener('popstate', checkUrlChange);
  setInterval(checkUrlChange, 1500);

  // Initial check when script loads
  setTimeout(checkAndProcessQueue, 2000);
}

// Listen to direct IPC commands without reloading the tab
if (typeof chrome !== 'undefined' && chrome.runtime?.onMessage) {
  chrome.runtime.onMessage.addListener((message: IPCMessage, sender, sendResponse) => {
    if (message.type === 'NAVIGATE_AND_SEND') {
      handleNavigateAndSend(message.payload);
      sendResponse({ received: true });
      return true;
    }
    if (message.type === 'TOGGLE_SIDEBAR') {
      sidebarInstance.toggleSidebar();
      sendResponse({ success: true });
      return true;
    }
    if (message.type === 'OPEN_SIDEBAR') {
      sidebarInstance.toggleSidebar(true);
      sendResponse({ success: true });
      return true;
    }
    if (message.type === 'AUTH_CHANGED') {
      sidebarInstance.refreshAuth();
      sendResponse({ success: true });
      return true;
    }
  });
}
