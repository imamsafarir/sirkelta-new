import { QueueState, ContactItem, SenderSettings, ContactStatus, AttachmentItem } from '../shared/types';
import { randomBetween, sleep } from '../shared/sleeper';

const STORAGE_KEY = 'wa_sender_queue_state';

const defaultState: QueueState = {
  runStatus: 'IDLE',
  template: '',
  contacts: [],
  currentIndex: 0,
  settings: {
    minDelaySec: 5,
    maxDelaySec: 10,
    minTypingSpeedMs: 50,
    maxTypingSpeedMs: 120
  }
};

export class QueueManager {
  private state: QueueState = {
    ...defaultState,
    contacts: [...defaultState.contacts],
    settings: { ...defaultState.settings }
  };

  constructor() {
    this.loadState();
  }

  async loadState(): Promise<QueueState> {
    return new Promise((resolve) => {
      chrome.storage.local.get([STORAGE_KEY], (res) => {
        if (res && res[STORAGE_KEY]) {
          this.state = res[STORAGE_KEY];
        }
        resolve(this.state);
      });
    });
  }

  async saveState(): Promise<void> {
    return new Promise((resolve) => {
      chrome.storage.local.set({ [STORAGE_KEY]: this.state }, () => {
        resolve();
      });
    });
  }

  async getState(): Promise<QueueState> {
    await this.loadState();
    return this.state;
  }

  async startQueue(
    contacts: ContactItem[],
    template: string,
    settings: SenderSettings,
    appendRandomCode?: boolean,
    attachments?: AttachmentItem[]
  ): Promise<void> {
    this.state = {
      runStatus: 'RUNNING',
      template,
      appendRandomCode,
      attachments,
      contacts,
      currentIndex: 0,
      settings,
      isCooldown: false,
      cooldownRemainingSec: 0,
      batchSize: 10,
      batchCooldownSec: 35
    };
    await this.saveState();
    await this.processNextContact();
  }

  async pauseQueue(): Promise<void> {
    await this.loadState();
    this.state.runStatus = 'PAUSED';
    this.state.isCooldown = false;
    await this.saveState();
  }

  async resumeQueue(): Promise<void> {
    await this.loadState();
    if (this.state.runStatus === 'PAUSED') {
      this.state.runStatus = 'RUNNING';
      this.state.isCooldown = false;
      await this.saveState();
      await this.processNextContact();
    }
  }

  async stopQueue(): Promise<void> {
    await this.loadState();
    this.state.runStatus = 'IDLE';
    this.state.isCooldown = false;
    await this.saveState();
  }

  async updateContactStatus(phone: string, status: ContactStatus, errorMessage?: string): Promise<void> {
    await this.loadState();
    let contact = this.state.contacts[this.state.currentIndex];
    if (!contact || contact.phone !== phone) {
      contact = this.state.contacts.find((c) => c.phone === phone);
    }
    if (contact) {
      contact.status = status;
      contact.sentAt = new Date().toLocaleTimeString();
      contact.errorMessage = errorMessage;
    }

    this.state.currentIndex += 1;

    if (this.state.currentIndex >= this.state.contacts.length) {
      this.state.runStatus = 'COMPLETED';
      this.state.isCooldown = false;
      await this.saveState();
      return;
    }

    await this.saveState();

    if (this.state.runStatus === 'RUNNING') {
      const batchSize = this.state.batchSize || 10;
      const isBatchBreak = this.state.currentIndex > 0 && this.state.currentIndex % batchSize === 0;

      if (isBatchBreak) {
        // Natural cooling break after every batch of messages
        const cooldownBase = this.state.batchCooldownSec || 35;
        const cooldownSec = randomBetween(cooldownBase, cooldownBase + 10);
        this.state.isCooldown = true;
        this.state.cooldownRemainingSec = cooldownSec;
        await this.saveState();

        for (let sec = cooldownSec; sec > 0; sec--) {
          await this.loadState();
          if (this.state.runStatus !== 'RUNNING') {
            this.state.isCooldown = false;
            await this.saveState();
            return;
          }
          this.state.cooldownRemainingSec = sec;
          await this.saveState();
          await sleep(1000);
        }

        this.state.isCooldown = false;
        this.state.cooldownRemainingSec = 0;
        await this.saveState();
      } else {
        // Random delay with sub-second decimal jitter to avoid robotic fixed intervals
        const minMs = this.state.settings.minDelaySec * 1000;
        const maxMs = this.state.settings.maxDelaySec * 1000;
        const delayMs = randomBetween(minMs, maxMs) + randomBetween(100, 800);
        await sleep(delayMs);
      }

      // Recheck status in case user clicked Pause or Stop during delay
      await this.loadState();
      if (this.state.runStatus === 'RUNNING') {
        await this.processNextContact();
      }
    }
  }

  private async findOrCreateWhatsAppTab(): Promise<chrome.tabs.Tab | null> {
    return new Promise((resolve) => {
      chrome.tabs.query({ url: '*://web.whatsapp.com/*' }, (tabs) => {
        if (tabs && tabs.length > 0) {
          // Prefer currently active tab if multiple WhatsApp tabs exist
          const activeTab = tabs.find((t) => t.active) || tabs[0];
          resolve(activeTab);
        } else {
          chrome.tabs.create({ url: 'https://web.whatsapp.com' }, (newTab) => {
            resolve(newTab || null);
          });
        }
      });
    });
  }

  async processNextContact(): Promise<void> {
    if (this.state.runStatus !== 'RUNNING') return;
    if (this.state.currentIndex >= this.state.contacts.length) {
      this.state.runStatus = 'COMPLETED';
      await this.saveState();
      return;
    }

    const currentContact = this.state.contacts[this.state.currentIndex];
    currentContact.status = 'SENDING';
    await this.saveState();

    const tab = await this.findOrCreateWhatsAppTab();
    if (!tab || !tab.id) {
      await this.updateContactStatus(currentContact.phone, 'FAILED_TIMEOUT', 'Tab WhatsApp Web tidak ditemukan');
      return;
    }

    this.state.activeTabId = tab.id;
    await this.saveState();

    // Bring tab to the front WITHOUT reloading the page
    chrome.tabs.update(tab.id, { active: true });

    // Send direct in-page command without reloading the entire WhatsApp Web page!
    const sendPayload = {
      type: 'NAVIGATE_AND_SEND' as const,
      payload: {
        contact: currentContact,
        template: this.state.template,
        settings: this.state.settings,
        appendRandomCode: this.state.appendRandomCode,
        attachments: this.state.attachments
      }
    };

    chrome.tabs.sendMessage(tab.id, sendPayload, () => {
      if (chrome.runtime?.lastError) {
        console.warn('Content script not answering, injecting dynamically into tab:', chrome.runtime.lastError.message);
        // If content script was not loaded (e.g. tab opened before extension reloaded),
        // dynamically inject script without reloading the tab!
        if (chrome.scripting && tab.id) {
          chrome.scripting.executeScript(
            {
              target: { tabId: tab.id },
              files: ['src/content/content-script.js']
            },
            () => {
              setTimeout(() => {
                if (tab.id) {
                  chrome.tabs.sendMessage(tab.id, sendPayload, () => {
                    if (chrome.runtime?.lastError) {
                      // ignore
                    }
                  });
                }
              }, 300);
            }
          );
        }
      }
    });
  }
}
