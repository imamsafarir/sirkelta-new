# WhatsApp Web Typing & Auto-Sender Chrome Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun ekstensi Google Chrome (Manifest V3) berbasis TypeScript, Vite, dan Tailwind CSS untuk otomatisasi pengiriman pesan WhatsApp Web massal dengan simulasi pengetikan alami (human-like typing), template dinamis `{nama}`, dan pengelolaan jeda aman.

**Architecture:** Arsitektur terbagi menjadi tiga komponen utama Chrome Extension Manifest V3:
1. **Popup UI** (`src/popup/`): Antarmuka pengaturan, input kontak, template pesan dengan pratinjau langsung, dan pemantauan progres real-time.
2. **Background Service Worker** (`src/background/`): Pengatur antrean sentral tersimpan di `chrome.storage.local` yang mengarahkan navigasi tab WhatsApp Web dan mengatur jeda waktu antar nomor.
3. **Content Script** (`src/content/`): Script yang diinjeksi ke WhatsApp Web untuk mendeteksi kesiapan chat, menyimulasikan pengetikan per karakter secara realistis, memicu pengiriman pesan, serta menangani dialog nomor tidak valid.

**Tech Stack:** TypeScript, Vite, Tailwind CSS, Vitest, Chrome Extension Manifest V3 API.

**Spec:** [docs/superpowers/specs/2026-10-20-whatsapp-sender-extension-design.md](file:///c:/Users/HP/Herd/wa-sender-extension/docs/superpowers/specs/2026-10-20-whatsapp-sender-extension-design.md)

## Global Constraints

- Standar ekstensi Chrome: Manifest V3 (`manifest_version: 3`).
- Bahasa pemrograman: TypeScript murni tanpa framework berat untuk menjaga ukuran ekstensi seringan mungkin.
- Seluruh state antrean harus dipersistensikan di `chrome.storage.local` agar pengiriman tetap berjalan jika popup ditutup.
- Pengetikan pesan di WhatsApp Web wajib menggunakan simulasi karakter demi karakter (bukan `innerText = text` instan).
- Seluruh penanganan nomor dan pesan diproses 100% lokal di browser pengguna (tanpa koneksi server eksternal / third-party analytics).

---

### Task 1: Project Scaffolding, Tooling & Build Pipeline

**Files:**
- Create: `package.json`
- Create: `tsconfig.json`
- Create: `vite.config.ts`
- Create: `tailwind.config.js`
- Create: `postcss.config.js`
- Create: `src/manifest.json`
- Create: `src/types/chrome.d.ts`
- Test: `tests/scaffold.test.ts`

**Interfaces:**
- Produces: Konfigurasi build pipeline Vite yang mengompilasi `src/popup/index.html`, `src/background/service-worker.ts`, dan `src/content/content-script.ts` ke folder `dist/` dengan `manifest.json`.

- [ ] **Step 1: Write test to verify build and manifest configuration**

```typescript
// tests/scaffold.test.ts
import { describe, it, expect } from 'vitest';
import manifest from '../src/manifest.json';

describe('Manifest V3 Configuration', () => {
  it('should have valid Manifest V3 structure', () => {
    expect(manifest.manifest_version).toBe(3);
    expect(manifest.name).toBe('WhatsApp Web Typing & Auto-Sender');
    expect(manifest.version).toBe('1.0.0');
    expect(manifest.permissions).toContain('storage');
    expect(manifest.permissions).toContain('tabs');
    expect(manifest.host_permissions).toContain('https://web.whatsapp.com/*');
    expect(manifest.action.default_popup).toBe('src/popup/index.html');
    expect(manifest.background.service_worker).toBe('src/background/service-worker.ts');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run tests/scaffold.test.ts`
Expected: FAIL (files and dependencies do not exist yet).

- [ ] **Step 3: Create package.json and configuration files**

Create `package.json`:
```json
{
  "name": "wa-sender-extension",
  "version": "1.0.0",
  "private": true,
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "test": "vitest run"
  },
  "devDependencies": {
    "@types/chrome": "^0.0.268",
    "@types/node": "^20.11.24",
    "autoprefixer": "^10.4.19",
    "postcss": "^8.4.38",
    "tailwindcss": "^3.4.3",
    "typescript": "^5.4.5",
    "vite": "^5.2.11",
    "vitest": "^1.6.0"
  }
}
```

Create `tsconfig.json`:
```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "resolveJsonModule": true,
    "types": ["chrome", "node", "vitest/globals"]
  },
  "include": ["src/**/*", "tests/**/*"]
}
```

Create `tailwind.config.js`:
```javascript
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/**/*.{html,ts,tsx,js}"
  ],
  theme: {
    extend: {
      colors: {
        wa: {
          light: '#25D366',
          DEFAULT: '#128C7E',
          dark: '#075E54',
          bg: '#EFEAE2'
        }
      }
    },
  },
  plugins: [],
}
```

Create `postcss.config.js`:
```javascript
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
}
```

Create `src/manifest.json`:
```json
{
  "manifest_version": 3,
  "name": "WhatsApp Web Typing & Auto-Sender",
  "version": "1.0.0",
  "description": "Asisten pengetikan dan pengiriman pesan otomatis massal di WhatsApp Web.",
  "permissions": [
    "storage",
    "tabs"
  ],
  "host_permissions": [
    "https://web.whatsapp.com/*"
  ],
  "action": {
    "default_popup": "src/popup/index.html",
    "default_title": "WA Auto-Sender"
  },
  "background": {
    "service_worker": "src/background/service-worker.ts",
    "type": "module"
  },
  "content_scripts": [
    {
      "matches": ["https://web.whatsapp.com/*"],
      "js": ["src/content/content-script.ts"],
      "run_at": "document_idle"
    }
  ]
}
```

Create `vite.config.ts`:
```typescript
import { defineConfig } from 'vite';
import { resolve } from 'path';
import { copyFileSync, existsSync, mkdirSync } from 'fs';

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        popup: resolve(__dirname, 'src/popup/index.html'),
        background: resolve(__dirname, 'src/background/service-worker.ts'),
        content: resolve(__dirname, 'src/content/content-script.ts')
      },
      output: {
        entryFileNames: (chunkInfo) => {
          if (chunkInfo.name === 'background') return 'src/background/service-worker.js';
          if (chunkInfo.name === 'content') return 'src/content/content-script.js';
          return 'assets/[name]-[hash].js';
        },
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    }
  },
  plugins: [
    {
      name: 'copy-manifest',
      closeBundle() {
        if (!existsSync('dist')) {
          mkdirSync('dist', { recursive: true });
        }
        copyFileSync('src/manifest.json', 'dist/manifest.json');
      }
    }
  ]
});
```

Install dependencies:
`npm install`

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run tests/scaffold.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add package.json tsconfig.json vite.config.ts tailwind.config.js postcss.config.js src/manifest.json tests/scaffold.test.ts
git commit -m "chore: setup project scaffolding and Manifest V3 configuration"
```

---

### Task 2: Shared Utilities & Data Logic (Normalizer, Template Parser, Types)

**Files:**
- Create: `src/shared/types.ts`
- Create: `src/shared/normalizer.ts`
- Create: `src/shared/template.ts`
- Create: `src/shared/sleeper.ts`
- Test: `tests/shared/normalizer.test.ts`
- Test: `tests/shared/template.test.ts`

**Interfaces:**
- Produces:
  - `cleanPhoneNumber(input: string): string`
  - `parseContactList(rawInput: string): ContactItem[]`
  - `renderTemplate(template: string, contact: ContactItem): string`
  - `randomBetween(min: number, max: number): number`
  - `sleep(ms: number): Promise<void>`
  - Types: `ContactItem`, `QueueState`, `SenderSettings`, `IPCMessage`

- [ ] **Step 1: Write failing unit tests for normalizer & template parser**

```typescript
// tests/shared/normalizer.test.ts
import { describe, it, expect } from 'vitest';
import { cleanPhoneNumber, parseContactList } from '../../src/shared/normalizer';

describe('Normalizer utilities', () => {
  it('cleans 08xx Indonesian numbers to 628xx format', () => {
    expect(cleanPhoneNumber('081234567890')).toBe('6281234567890');
    expect(cleanPhoneNumber('+62 812-3456-7890')).toBe('6281234567890');
    expect(cleanPhoneNumber('6281234567890')).toBe('6281234567890');
    expect(cleanPhoneNumber('81234567890')).toBe('6281234567890');
  });

  it('parses multiple contact lines with name and phone', () => {
    const raw = `
      081234567890, Budi Santoso
      +62 898-7654-3210, Siti
      085611122233
    `;
    const result = parseContactList(raw);
    expect(result).toHaveLength(3);
    expect(result[0]).toEqual({
      id: expect.any(String),
      phone: '6281234567890',
      name: 'Budi Santoso',
      status: 'PENDING'
    });
    expect(result[1]).toEqual({
      id: expect.any(String),
      phone: '6289876543210',
      name: 'Siti',
      status: 'PENDING'
    });
    expect(result[2]).toEqual({
      id: expect.any(String),
      phone: '6285611122233',
      name: '',
      status: 'PENDING'
    });
  });
});
```

```typescript
// tests/shared/template.test.ts
import { describe, it, expect } from 'vitest';
import { renderTemplate } from '../../src/shared/template';
import { ContactItem } from '../../src/shared/types';

describe('Template parser', () => {
  it('replaces {nama} with contact name', () => {
    const contact: ContactItem = {
      id: '1',
      phone: '6281234567890',
      name: 'Andi',
      status: 'PENDING'
    };
    const template = 'Halo kak {nama}, paket Anda sedang dikirim ke {nama}.';
    expect(renderTemplate(template, contact)).toBe('Halo kak Andi, paket Anda sedang dikirim ke Andi.');
  });

  it('handles empty contact name gracefully', () => {
    const contact: ContactItem = {
      id: '2',
      phone: '6281234567890',
      name: '',
      status: 'PENDING'
    };
    const template = 'Halo {nama}, selamat datang!';
    expect(renderTemplate(template, contact)).toBe('Halo Kak/Bapak/Ibu, selamat datang!');
  });
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `npx vitest run tests/shared/`
Expected: FAIL (modules not created).

- [ ] **Step 3: Implement shared types and functions**

Create `src/shared/types.ts`:
```typescript
export type ContactStatus = 'PENDING' | 'SENDING' | 'SUCCESS' | 'FAILED_INVALID' | 'FAILED_TIMEOUT' | 'SKIPPED';

export interface ContactItem {
  id: string;
  phone: string;
  name: string;
  status: ContactStatus;
  sentAt?: string;
  errorMessage?: string;
}

export type QueueRunStatus = 'IDLE' | 'RUNNING' | 'PAUSED' | 'COMPLETED';

export interface SenderSettings {
  minDelaySec: number;
  maxDelaySec: number;
  minTypingSpeedMs: number;
  maxTypingSpeedMs: number;
}

export interface QueueState {
  runStatus: QueueRunStatus;
  template: string;
  contacts: ContactItem[];
  currentIndex: number;
  settings: SenderSettings;
  activeTabId?: number;
}

export type IPCMessage =
  | { type: 'START_QUEUE'; payload: { contacts: ContactItem[]; template: string; settings: SenderSettings } }
  | { type: 'PAUSE_QUEUE' }
  | { type: 'RESUME_QUEUE' }
  | { type: 'STOP_QUEUE' }
  | { type: 'GET_QUEUE_STATE' }
  | { type: 'CONTENT_READY_TO_TYPE'; payload: { phone: string; text: string; minSpeed: number; maxSpeed: number } }
  | { type: 'CONTENT_RESULT'; payload: { phone: string; status: ContactStatus; errorMessage?: string } };
```

Create `src/shared/sleeper.ts`:
```typescript
export function randomBetween(min: number, max: number): number {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

export function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
```

Create `src/shared/normalizer.ts`:
```typescript
import { ContactItem } from './types';

export function cleanPhoneNumber(input: string): string {
  let cleaned = input.replace(/[^0-9]/g, '');
  if (cleaned.startsWith('08')) {
    cleaned = '628' + cleaned.slice(2);
  } else if (cleaned.startsWith('8')) {
    cleaned = '628' + cleaned.slice(1);
  } else if (cleaned.startsWith('0062')) {
    cleaned = '62' + cleaned.slice(4);
  }
  return cleaned;
}

export function parseContactList(rawInput: string): ContactItem[] {
  const lines = rawInput.split('\n');
  const contacts: ContactItem[] = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;

    const parts = line.split(/[,\t]/).map((p) => p.trim());
    const rawPhone = parts[0] || '';
    const name = parts.slice(1).join(' ').trim();
    const phone = cleanPhoneNumber(rawPhone);

    if (phone.length >= 8) {
      contacts.push({
        id: `contact-${Date.now()}-${i}-${Math.random().toString(36).slice(2, 6)}`,
        phone,
        name,
        status: 'PENDING'
      });
    }
  }

  return contacts;
}
```

Create `src/shared/template.ts`:
```typescript
import { ContactItem } from './types';

export function renderTemplate(template: string, contact: ContactItem, fallbackName: string = 'Kak/Bapak/Ibu'): string {
  const finalName = contact.name.trim() ? contact.name.trim() : fallbackName;
  return template.replace(/\{nama\}/gi, finalName);
}
```

- [ ] **Step 4: Run unit tests to verify they pass**

Run: `npx vitest run tests/shared/`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/shared/ tests/shared/
git commit -m "feat: implement shared types, normalizer, template parser, and sleeper"
```

---

### Task 3: Background Service Worker (Queue Orchestrator)

**Files:**
- Create: `src/background/service-worker.ts`
- Create: `src/background/queue-manager.ts`
- Test: `tests/background/queue-manager.test.ts`

**Interfaces:**
- Consumes: `QueueState`, `ContactItem`, `SenderSettings`, `IPCMessage` from `src/shared/types.ts`
- Produces: `QueueManager` class yang mengontrol siklus `START`, `PAUSE`, `STOP`, dan transisi item antrean dengan `chrome.storage.local`.

- [ ] **Step 1: Write test for QueueManager logic**

```typescript
// tests/background/queue-manager.test.ts
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { QueueManager } from '../../src/background/queue-manager';
import { ContactItem, SenderSettings } from '../../src/shared/types';

describe('QueueManager', () => {
  let manager: QueueManager;
  let mockStorage: Record<string, any>;

  beforeEach(() => {
    mockStorage = {};
    global.chrome = {
      storage: {
        local: {
          get: vi.fn((keys, cb) => cb ? cb(mockStorage) : Promise.resolve(mockStorage)),
          set: vi.fn((items, cb) => {
            Object.assign(mockStorage, items);
            if (cb) cb();
            return Promise.resolve();
          })
        }
      },
      tabs: {
        query: vi.fn(),
        update: vi.fn(),
        create: vi.fn()
      }
    } as any;

    manager = new QueueManager();
  });

  it('initializes with default IDLE state', async () => {
    const state = await manager.getState();
    expect(state.runStatus).toBe('IDLE');
    expect(state.contacts).toHaveLength(0);
  });

  it('starts queue and sets status to RUNNING', async () => {
    const contacts: ContactItem[] = [
      { id: '1', phone: '628123', name: 'A', status: 'PENDING' },
      { id: '2', phone: '628124', name: 'B', status: 'PENDING' }
    ];
    const settings: SenderSettings = {
      minDelaySec: 5,
      maxDelaySec: 10,
      minTypingSpeedMs: 50,
      maxTypingSpeedMs: 120
    };

    await manager.startQueue(contacts, 'Halo {nama}', settings);
    const state = await manager.getState();
    expect(state.runStatus).toBe('RUNNING');
    expect(state.contacts).toHaveLength(2);
    expect(state.currentIndex).toBe(0);
  });

  it('pauses and stops queue correctly', async () => {
    await manager.pauseQueue();
    let state = await manager.getState();
    expect(state.runStatus).toBe('PAUSED');

    await manager.stopQueue();
    state = await manager.getState();
    expect(state.runStatus).toBe('IDLE');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run tests/background/queue-manager.test.ts`
Expected: FAIL (QueueManager not defined).

- [ ] **Step 3: Implement QueueManager and Service Worker**

Create `src/background/queue-manager.ts`:
```typescript
import { QueueState, ContactItem, SenderSettings, ContactStatus } from '../shared/types';
import { randomBetween, sleep } from '../shared/sleeper';
import { renderTemplate } from '../shared/template';

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
  private state: QueueState = { ...defaultState };

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

  async startQueue(contacts: ContactItem[], template: string, settings: SenderSettings): Promise<void> {
    this.state = {
      runStatus: 'RUNNING',
      template,
      contacts,
      currentIndex: 0,
      settings
    };
    await this.saveState();
    this.processNextContact();
  }

  async pauseQueue(): Promise<void> {
    this.state.runStatus = 'PAUSED';
    await this.saveState();
  }

  async resumeQueue(): Promise<void> {
    if (this.state.runStatus === 'PAUSED') {
      this.state.runStatus = 'RUNNING';
      await this.saveState();
      this.processNextContact();
    }
  }

  async stopQueue(): Promise<void> {
    this.state.runStatus = 'IDLE';
    await this.saveState();
  }

  async updateContactStatus(phone: string, status: ContactStatus, errorMessage?: string): Promise<void> {
    const contact = this.state.contacts.find((c) => c.phone === phone);
    if (contact) {
      contact.status = status;
      contact.sentAt = new Date().toLocaleTimeString();
      contact.errorMessage = errorMessage;
    }
    await this.saveState();

    if (this.state.runStatus === 'RUNNING') {
      this.state.currentIndex += 1;
      await this.saveState();

      if (this.state.currentIndex >= this.state.contacts.length) {
        this.state.runStatus = 'COMPLETED';
        await this.saveState();
        return;
      }

      // Random delay before next contact
      const delaySec = randomBetween(this.state.settings.minDelaySec, this.state.settings.maxDelaySec);
      await sleep(delaySec * 1000);

      // Recheck status in case user clicked Pause or Stop during delay
      await this.loadState();
      if (this.state.runStatus === 'RUNNING') {
        this.processNextContact();
      }
    }
  }

  private async findOrCreateWhatsAppTab(): Promise<chrome.tabs.Tab | null> {
    return new Promise((resolve) => {
      chrome.tabs.query({ url: '*://web.whatsapp.com/*' }, (tabs) => {
        if (tabs && tabs.length > 0 && tabs[0].id) {
          resolve(tabs[0]);
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

    // Navigate to specific contact URL
    const targetUrl = `https://web.whatsapp.com/send?phone=${currentContact.phone}`;
    chrome.tabs.update(tab.id, { url: targetUrl });
  }
}
```

Create `src/background/service-worker.ts`:
```typescript
import { QueueManager } from './queue-manager';
import { IPCMessage } from '../shared/types';
import { renderTemplate } from '../shared/template';

const queueManager = new QueueManager();

chrome.runtime.onMessage.addListener((message: IPCMessage, sender, sendResponse) => {
  (async () => {
    switch (message.type) {
      case 'START_QUEUE':
        await queueManager.startQueue(
          message.payload.contacts,
          message.payload.template,
          message.payload.settings
        );
        sendResponse({ success: true });
        break;

      case 'PAUSE_QUEUE':
        await queueManager.pauseQueue();
        sendResponse({ success: true });
        break;

      case 'RESUME_QUEUE':
        await queueManager.resumeQueue();
        sendResponse({ success: true });
        break;

      case 'STOP_QUEUE':
        await queueManager.stopQueue();
        sendResponse({ success: true });
        break;

      case 'GET_QUEUE_STATE':
        const state = await queueManager.getState();
        sendResponse({ state });
        break;

      case 'CONTENT_RESULT':
        await queueManager.updateContactStatus(
          message.payload.phone,
          message.payload.status,
          message.payload.errorMessage
        );
        sendResponse({ success: true });
        break;

      default:
        sendResponse({ error: 'Unknown message type' });
    }
  })();
  return true; // Keep message channel open for async response
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run tests/background/queue-manager.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/background/ tests/background/
git commit -m "feat: implement background queue manager and service worker"
```

---

### Task 4: WhatsApp Web Content Script (DOM Typing, Send Trigger, Invalid Number Handler)

**Files:**
- Create: `src/content/content-script.ts`
- Create: `src/content/dom-actions.ts`
- Test: `tests/content/dom-actions.test.ts`

**Interfaces:**
- Produces:
  - `waitForElement(selector: string, timeoutMs?: number): Promise<HTMLElement | null>`
  - `simulateHumanTyping(element: HTMLElement, text: string, minSpeed: number, maxSpeed: number): Promise<void>`
  - `clickSendButton(): Promise<boolean>`
  - `detectInvalidNumberDialog(): Promise<boolean>`

- [ ] **Step 1: Write test for dom-actions helper functions**

```typescript
// tests/content/dom-actions.test.ts
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { simulateHumanTyping } from '../../src/content/dom-actions';

describe('Content Script DOM Actions', () => {
  let mockDiv: HTMLDivElement;

  beforeEach(() => {
    mockDiv = document.createElement('div');
    mockDiv.contentEditable = 'true';
    document.body.appendChild(mockDiv);
    document.execCommand = vi.fn((cmd, showUI, value) => {
      if (cmd === 'insertText') {
        mockDiv.textContent = (mockDiv.textContent || '') + value;
        return true;
      }
      return false;
    });
  });

  it('types text character by character into contenteditable div', async () => {
    await simulateHumanTyping(mockDiv, 'Halo', 1, 5);
    expect(mockDiv.textContent).toBe('Halo');
    expect(document.execCommand).toHaveBeenCalledTimes(4);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npx vitest run tests/content/dom-actions.test.ts`
Expected: FAIL (dom-actions not implemented).

- [ ] **Step 3: Implement dom-actions and content-script**

Create `src/content/dom-actions.ts`:
```typescript
import { randomBetween, sleep } from '../shared/sleeper';

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
    if (
      text.includes('tidak valid') ||
      text.includes('invalid') ||
      text.includes('URL') ||
      text.includes('tautan')
    ) {
      const okButton = dialog.querySelector<HTMLElement>('button');
      return okButton || null;
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

    const delay = randomBetween(minSpeedMs, maxSpeedMs);
    await sleep(delay);
  }

  // Trigger input change event
  element.dispatchEvent(new Event('input', { bubbles: true }));
}

export async function clickSendButton(): Promise<boolean> {
  // Selector options for WhatsApp Web send button
  const sendIcon = document.querySelector('span[data-icon="send"]') ||
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
```

Create `src/content/content-script.ts`:
```typescript
import { waitForElement, detectInvalidNumberDialog, simulateHumanTyping, clickSendButton } from './dom-actions';
import { renderTemplate } from '../shared/template';
import { sleep } from '../shared/sleeper';
import { QueueState } from '../shared/types';

const CHAT_INPUT_SELECTOR = 'footer div[contenteditable="true"][role="textbox"]';

async function checkAndProcessQueue(): Promise<void> {
  // Check if we are on a send page: https://web.whatsapp.com/send?phone=...
  const urlParams = new URLSearchParams(window.location.search);
  const currentPhone = urlParams.get('phone');
  if (!currentPhone) return;

  // Request current queue state from background
  chrome.runtime.sendMessage({ type: 'GET_QUEUE_STATE' }, async (response) => {
    if (!response || !response.state) return;
    const state: QueueState = response.state;

    if (state.runStatus !== 'RUNNING') return;

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
          payload: { phone: currentPhone, status: 'FAILED_INVALID', errorMessage: 'Nomor tidak terdaftar di WhatsApp' }
        });
        return;
      }

      // Check if chat input is available
      const inputEl = document.querySelector<HTMLElement>(CHAT_INPUT_SELECTOR);
      if (inputEl) {
        // Chat is open and ready!
        const messageText = renderTemplate(state.template, currentContact);

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
      payload: { phone: currentPhone, status: 'FAILED_TIMEOUT', errorMessage: 'Waktu tunggu obrolan habis (timeout)' }
    });
  });
}

// Listen to URL change / history events on WhatsApp Web
let lastUrl = location.href;
new MutationObserver(() => {
  const url = location.href;
  if (url !== lastUrl) {
    lastUrl = url;
    setTimeout(checkAndProcessQueue, 1500);
  }
}).observe(document, { subtree: true, childList: true });

// Initial check when script loads
setTimeout(checkAndProcessQueue, 2000);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npx vitest run tests/content/dom-actions.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/content/ tests/content/
git commit -m "feat: implement content script DOM interaction and human typing simulation"
```

---

### Task 5: Popup Toolbar UI (Tailwind CSS, Form, Controls, Real-time Status)

**Files:**
- Create: `src/popup/popup.css`
- Create: `src/popup/index.html`
- Create: `src/popup/popup.ts`
- Test: `tests/popup/popup-ui.test.ts`

**Interfaces:**
- Produces:
  - Antarmuka pengguna lengkap dengan input textarea, upload CSV, template dengan `{nama}`, live preview box, delay settings, control buttons, dan log tabel pengiriman.

- [ ] **Step 1: Write test for popup contact parsing and live preview formatting**

```typescript
// tests/popup/popup-ui.test.ts
import { describe, it, expect } from 'vitest';
import { parseContactList } from '../../src/shared/normalizer';
import { renderTemplate } from '../../src/shared/template';

describe('Popup UI logic', () => {
  it('updates live preview when template and contact list change', () => {
    const rawContacts = '081234567890, Budi Hartono';
    const contacts = parseContactList(rawContacts);
    const template = 'Selamat siang {nama}, tagihan Anda sudah terbit.';

    const preview = renderTemplate(template, contacts[0]);
    expect(preview).toBe('Selamat siang Budi Hartono, tagihan Anda sudah terbit.');
  });
});
```

- [ ] **Step 2: Run test to verify it passes**

Run: `npx vitest run tests/popup/popup-ui.test.ts`
Expected: PASS

- [ ] **Step 3: Implement Popup CSS, HTML, and TypeScript Controller**

Create `src/popup/popup.css`:
```css
@tailwind base;
@tailwind components;
@tailwind utilities;

body {
  width: 440px;
  min-height: 560px;
  margin: 0;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}
```

Create `src/popup/index.html`:
```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WhatsApp Web Auto-Sender</title>
  <link rel="stylesheet" href="./popup.css">
</head>
<body class="bg-slate-50 text-slate-800 p-4">
  <!-- Header -->
  <header class="flex items-center justify-between pb-3 border-b border-slate-200">
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center text-white font-bold text-lg">
        WA
      </div>
      <div>
        <h1 class="font-bold text-sm text-slate-900">WhatsApp Web Sender</h1>
        <p class="text-xs text-slate-500">Auto-Typing & Broadcast Assistant</p>
      </div>
    </div>
    <div id="waStatusBadge" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
      <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
      <span id="waStatusText">Memeriksa WA Web...</span>
    </div>
  </header>

  <!-- Open WA Web Banner (if not open) -->
  <div id="openWaBanner" class="hidden mt-3 p-2.5 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
    <span class="text-xs text-blue-700">Tab WhatsApp Web belum aktif</span>
    <button id="btnOpenWa" class="px-2.5 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition">
      Buka WhatsApp Web
    </button>
  </div>

  <main class="mt-3 space-y-3.5">
    <!-- Contacts Input Section -->
    <section>
      <div class="flex items-center justify-between mb-1">
        <label for="contactsInput" class="text-xs font-semibold text-slate-700">
          Daftar Kontak (Nomor, Nama)
        </label>
        <span id="contactCountBadge" class="text-xs text-slate-500 font-mono">0 kontak</span>
      </div>
      <textarea
        id="contactsInput"
        rows="3"
        class="w-full text-xs p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none font-mono"
        placeholder="081234567890, Budi&#10;089876543210, Siti"></textarea>
      <div class="flex items-center justify-between mt-1 text-[11px] text-slate-500">
        <span>Format: <code>081234xxx, Nama</code></span>
        <label class="cursor-pointer text-emerald-600 hover:underline">
          Impor CSV
          <input type="file" id="csvFileInput" accept=".csv,.txt" class="hidden">
        </label>
      </div>
    </section>

    <!-- Message Template Section -->
    <section>
      <div class="flex items-center justify-between mb-1">
        <label for="templateInput" class="text-xs font-semibold text-slate-700">Template Pesan</label>
        <button id="btnInsertName" type="button" class="text-[11px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100">
          + Sisipkan {nama}
        </button>
      </div>
      <textarea
        id="templateInput"
        rows="3"
        class="w-full text-xs p-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:outline-none"
        placeholder="Halo kak {nama}, terima kasih telah menghubungi kami...">Halo kak {nama}, terima kasih telah menghubungi kami.</textarea>
      
      <!-- Live Preview -->
      <div class="mt-1.5 p-2 bg-emerald-50 border border-emerald-100 rounded text-[11px] text-slate-700">
        <span class="font-semibold text-emerald-800 block text-[10px] uppercase">Pratinjau Pesan Pertama:</span>
        <p id="templatePreview" class="italic text-slate-600 mt-0.5 whitespace-pre-wrap"></p>
      </div>
    </section>

    <!-- Settings: Delay & Speed -->
    <section class="grid grid-cols-2 gap-2 p-2.5 bg-slate-100 rounded-lg text-xs">
      <div>
        <label class="font-medium text-slate-700 block mb-1">Jeda Antar Nomor (detik)</label>
        <div class="flex items-center gap-1">
          <input id="minDelay" type="number" min="2" max="60" value="5" class="w-12 p-1 border rounded text-center text-xs">
          <span>-</span>
          <input id="maxDelay" type="number" min="3" max="120" value="10" class="w-12 p-1 border rounded text-center text-xs">
        </div>
      </div>
      <div>
        <label class="font-medium text-slate-700 block mb-1">Kecepatan Ketik (ms)</label>
        <div class="flex items-center gap-1">
          <input id="minSpeed" type="number" min="20" max="300" value="50" class="w-12 p-1 border rounded text-center text-xs">
          <span>-</span>
          <input id="maxSpeed" type="number" min="40" max="500" value="120" class="w-12 p-1 border rounded text-center text-xs">
        </div>
      </div>
    </section>

    <!-- Progress & Counters -->
    <section id="progressSection" class="p-2.5 bg-white border border-slate-200 rounded-lg space-y-1.5">
      <div class="flex items-center justify-between text-xs font-semibold">
        <span id="queueStatusText">Siap Mengirim</span>
        <span id="progressPercentage" class="font-mono text-emerald-600">0%</span>
      </div>
      <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
        <div id="progressBar" class="bg-emerald-500 h-2 transition-all duration-300" style="width: 0%"></div>
      </div>
      <div class="grid grid-cols-3 text-center text-[11px] pt-1 border-t border-slate-100">
        <div>Total: <span id="counterTotal" class="font-bold">0</span></div>
        <div class="text-emerald-600">Sukses: <span id="counterSuccess" class="font-bold">0</span></div>
        <div class="text-rose-600">Gagal: <span id="counterFailed" class="font-bold">0</span></div>
      </div>
    </section>

    <!-- Control Buttons -->
    <div class="flex items-center gap-2 pt-1">
      <button
        id="btnStart"
        class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-xs shadow-sm transition">
        Mulai Kirim
      </button>
      <button
        id="btnPause"
        disabled
        class="hidden py-2 px-3 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg text-xs shadow-sm transition disabled:opacity-50">
        Jeda
      </button>
      <button
        id="btnResume"
        disabled
        class="hidden py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-xs shadow-sm transition disabled:opacity-50">
        Lanjutkan
      </button>
      <button
        id="btnStop"
        disabled
        class="py-2 px-3 bg-rose-600 hover:bg-rose-700 text-white font-semibold rounded-lg text-xs shadow-sm transition disabled:opacity-50">
        Hentikan
      </button>
    </div>
  </main>

  <script type="module" src="./popup.ts"></script>
</body>
</html>
```

Create `src/popup/popup.ts`:
```typescript
import { parseContactList } from '../shared/normalizer';
import { renderTemplate } from '../shared/template';
import { QueueState, SenderSettings } from '../shared/types';

// Elements
const contactsInput = document.getElementById('contactsInput') as HTMLTextAreaElement;
const contactCountBadge = document.getElementById('contactCountBadge') as HTMLSpanElement;
const csvFileInput = document.getElementById('csvFileInput') as HTMLInputElement;
const templateInput = document.getElementById('templateInput') as HTMLTextAreaElement;
const templatePreview = document.getElementById('templatePreview') as HTMLParagraphElement;
const btnInsertName = document.getElementById('btnInsertName') as HTMLButtonElement;

const minDelay = document.getElementById('minDelay') as HTMLInputElement;
const maxDelay = document.getElementById('maxDelay') as HTMLInputElement;
const minSpeed = document.getElementById('minSpeed') as HTMLInputElement;
const maxSpeed = document.getElementById('maxSpeed') as HTMLInputElement;

const waStatusBadge = document.getElementById('waStatusBadge') as HTMLDivElement;
const waStatusText = document.getElementById('waStatusText') as HTMLSpanElement;
const openWaBanner = document.getElementById('openWaBanner') as HTMLDivElement;
const btnOpenWa = document.getElementById('btnOpenWa') as HTMLButtonElement;

const queueStatusText = document.getElementById('queueStatusText') as HTMLSpanElement;
const progressPercentage = document.getElementById('progressPercentage') as HTMLSpanElement;
const progressBar = document.getElementById('progressBar') as HTMLDivElement;
const counterTotal = document.getElementById('counterTotal') as HTMLSpanElement;
const counterSuccess = document.getElementById('counterSuccess') as HTMLSpanElement;
const counterFailed = document.getElementById('counterFailed') as HTMLSpanElement;

const btnStart = document.getElementById('btnStart') as HTMLButtonElement;
const btnPause = document.getElementById('btnPause') as HTMLButtonElement;
const btnResume = document.getElementById('btnResume') as HTMLButtonElement;
const btnStop = document.getElementById('btnStop') as HTMLButtonElement;

function updatePreview() {
  const contacts = parseContactList(contactsInput.value);
  contactCountBadge.textContent = `${contacts.length} kontak`;

  if (contacts.length > 0) {
    templatePreview.textContent = renderTemplate(templateInput.value, contacts[0]);
  } else {
    templatePreview.textContent = renderTemplate(templateInput.value, {
      id: '0',
      phone: '628xxx',
      name: 'Nama Pelanggan',
      status: 'PENDING'
    });
  }
}

// Event Listeners
contactsInput.addEventListener('input', updatePreview);
templateInput.addEventListener('input', updatePreview);

btnInsertName.addEventListener('click', () => {
  const cursorPos = templateInput.selectionStart;
  const text = templateInput.value;
  templateInput.value = text.slice(0, cursorPos) + '{nama}' + text.slice(cursorPos);
  templateInput.focus();
  updatePreview();
});

csvFileInput.addEventListener('change', (e) => {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (event) => {
    contactsInput.value = event.target?.result as string;
    updatePreview();
  };
  reader.readAsText(file);
});

// Check WhatsApp Web Tab
function checkWhatsAppWebTab() {
  chrome.tabs.query({ url: '*://web.whatsapp.com/*' }, (tabs) => {
    if (tabs && tabs.length > 0) {
      waStatusBadge.className = 'flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800';
      waStatusText.textContent = 'WhatsApp Web Aktif';
      openWaBanner.classList.add('hidden');
    } else {
      waStatusBadge.className = 'flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800';
      waStatusText.textContent = 'WA Web Belum Dibuka';
      openWaBanner.classList.remove('hidden');
    }
  });
}

btnOpenWa.addEventListener('click', () => {
  chrome.tabs.create({ url: 'https://web.whatsapp.com' });
  setTimeout(checkWhatsAppWebTab, 1000);
});

// Sync State from Background
function renderQueueState(state: QueueState) {
  const total = state.contacts.length;
  const success = state.contacts.filter((c) => c.status === 'SUCCESS').length;
  const failed = state.contacts.filter((c) => c.status.startsWith('FAILED')).length;
  const completed = success + failed;

  counterTotal.textContent = total.toString();
  counterSuccess.textContent = success.toString();
  counterFailed.textContent = failed.toString();

  const percent = total > 0 ? Math.round((completed / total) * 100) : 0;
  progressBar.style.width = `${percent}%`;
  progressPercentage.textContent = `${percent}%`;

  if (state.runStatus === 'RUNNING') {
    queueStatusText.textContent = `Mengirim kontak ke-${state.currentIndex + 1} dari ${total}...`;
    btnStart.classList.add('hidden');
    btnPause.classList.remove('hidden');
    btnPause.disabled = false;
    btnResume.classList.add('hidden');
    btnStop.disabled = false;
  } else if (state.runStatus === 'PAUSED') {
    queueStatusText.textContent = 'Pengiriman dijeda';
    btnStart.classList.add('hidden');
    btnPause.classList.add('hidden');
    btnResume.classList.remove('hidden');
    btnResume.disabled = false;
    btnStop.disabled = false;
  } else if (state.runStatus === 'COMPLETED') {
    queueStatusText.textContent = 'Pengiriman selesai!';
    btnStart.classList.remove('hidden');
    btnPause.classList.add('hidden');
    btnResume.classList.add('hidden');
    btnStop.disabled = true;
  } else {
    queueStatusText.textContent = 'Siap Mengirim';
    btnStart.classList.remove('hidden');
    btnPause.classList.add('hidden');
    btnResume.classList.add('hidden');
    btnStop.disabled = true;
  }
}

function syncState() {
  chrome.runtime.sendMessage({ type: 'GET_QUEUE_STATE' }, (res) => {
    if (res && res.state) {
      renderQueueState(res.state);
    }
  });
}

// Start button
btnStart.addEventListener('click', () => {
  const contacts = parseContactList(contactsInput.value);
  if (contacts.length === 0) {
    alert('Silakan masukkan minimal 1 kontak.');
    return;
  }

  const settings: SenderSettings = {
    minDelaySec: parseInt(minDelay.value, 10) || 5,
    maxDelaySec: parseInt(maxDelay.value, 10) || 10,
    minTypingSpeedMs: parseInt(minSpeed.value, 10) || 50,
    maxTypingSpeedMs: parseInt(maxSpeed.value, 10) || 120
  };

  chrome.runtime.sendMessage({
    type: 'START_QUEUE',
    payload: {
      contacts,
      template: templateInput.value,
      settings
    }
  }, () => {
    syncState();
  });
});

btnPause.addEventListener('click', () => {
  chrome.runtime.sendMessage({ type: 'PAUSE_QUEUE' }, () => syncState());
});

btnResume.addEventListener('click', () => {
  chrome.runtime.sendMessage({ type: 'RESUME_QUEUE' }, () => syncState());
});

btnStop.addEventListener('click', () => {
  chrome.runtime.sendMessage({ type: 'STOP_QUEUE' }, () => syncState());
});

// Periodic check
checkWhatsAppWebTab();
updatePreview();
syncState();
setInterval(syncState, 1500);
```

- [ ] **Step 4: Commit**

```bash
git add src/popup/ tests/popup/
git commit -m "feat: implement popup UI, styling, and realtime queue controller"
```

---

### Task 6: End-to-End Build Verification & Extension Packaging

**Files:**
- Modify: `vite.config.ts` (if needed for asset bundling)
- Test: Build output directory `dist/` verification

- [ ] **Step 1: Run full test suite across all modules**

Run: `npm run test`
Expected: All unit tests in `tests/` pass with 0 errors.

- [ ] **Step 2: Run production build**

Run: `npm run build`
Expected: Successfully generates `dist/` containing `manifest.json`, `src/popup/index.html`, `src/background/service-worker.js`, `src/content/content-script.js`, and styling assets.

- [ ] **Step 3: Verify extension dist integrity**

Run check:
Verify that `dist/manifest.json` is present and valid.
Verify that `dist/src/background/service-worker.js` is present.
Verify that `dist/src/content/content-script.js` is present.
Verify that `dist/src/popup/index.html` is present.

- [ ] **Step 4: Commit**

```bash
git add .
git commit -m "chore: verify build pipeline and distribution artifacts"
```

