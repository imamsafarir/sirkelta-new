import { getApiBaseUrl } from '../shared/api';

export function normalizeRange(min: number, max: number): [number, number] {
  if (min > max) {
    return [max, min];
  }
  return [min, max];
}

export async function checkWhatsAppTab(): Promise<chrome.tabs.Tab | null> {
  if (typeof chrome === 'undefined' || !chrome.tabs?.query) {
    return null;
  }
  try {
    const tabs = await chrome.tabs.query({ url: '*://web.whatsapp.com/*' });
    return tabs && tabs.length > 0 ? tabs[0] : null;
  } catch {
    return null;
  }
}

export async function openWhatsAppWeb() {
  if (typeof chrome === 'undefined' || !chrome.tabs) {
    if (typeof window !== 'undefined') {
      window.open('https://web.whatsapp.com', '_blank');
      window.close();
    }
    return;
  }

  try {
    const waTab = await checkWhatsAppTab();
    if (waTab && waTab.id) {
      // Focus the existing tab and window
      await chrome.tabs.update(waTab.id, { active: true });
      if (waTab.windowId && chrome.windows?.update) {
        await chrome.windows.update(waTab.windowId, { focused: true });
      }
      try {
        await chrome.tabs.sendMessage(waTab.id, { type: 'OPEN_SIDEBAR' });
      } catch {
        // Tab might still be initializing
      }
    } else {
      await chrome.tabs.create({ url: 'https://web.whatsapp.com' });
    }
  } catch {
    if (typeof window !== 'undefined') {
      window.open('https://web.whatsapp.com', '_blank');
    }
  } finally {
    if (typeof window !== 'undefined' && window.close) {
      window.close();
    }
  }
}

export async function openDashboard() {
  let url = 'http://localhost:8000/wasender/dashboard';
  try {
    const apiBase = await getApiBaseUrl();
    const webBase = apiBase.replace(/\/api\/extension\/?$/, '');
    url = `${webBase}/wasender/dashboard`;
  } catch {
    url = 'http://localhost:8000/wasender/dashboard';
  }

  if (typeof chrome !== 'undefined' && chrome.tabs?.create) {
    chrome.tabs.create({ url });
  } else if (typeof window !== 'undefined') {
    window.open(url, '_blank');
  }
  if (typeof window !== 'undefined' && window.close) {
    window.close();
  }
}

export async function initPopup() {
  const btnOpenWhatsApp = document.getElementById('btnOpenWhatsApp') as HTMLButtonElement | null;
  const linkDashboard = document.getElementById('linkDashboard') as HTMLAnchorElement | null;
  const waStatusDot = document.getElementById('waStatusDot') as HTMLSpanElement | null;
  const waStatusText = document.getElementById('waStatusText') as HTMLSpanElement | null;
  const waStatusBadge = document.getElementById('waStatusBadge') as HTMLDivElement | null;

  btnOpenWhatsApp?.addEventListener('click', () => {
    openWhatsAppWeb();
  });

  linkDashboard?.addEventListener('click', (e) => {
    e.preventDefault();
    openDashboard();
  });

  // Check WA tab status
  const existingTab = await checkWhatsAppTab();
  if (existingTab) {
    if (waStatusDot) {
      waStatusDot.className = 'w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse';
    }
    if (waStatusText) {
      waStatusText.textContent = 'WA Terbuka';
    }
    if (waStatusBadge) {
      waStatusBadge.className = 'flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200';
    }
  } else {
    if (waStatusDot) {
      waStatusDot.className = 'w-1.5 h-1.5 rounded-full bg-slate-400';
    }
    if (waStatusText) {
      waStatusText.textContent = 'Belum Terbuka';
    }
    if (waStatusBadge) {
      waStatusBadge.className = 'flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200';
    }
  }
}

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initPopup());
  } else {
    initPopup();
  }
}
