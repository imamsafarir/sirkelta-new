// Web Auth Bridge
// Automatically synchronizes web login and logout states to the Chrome Extension

let lastSentAuthStatus: boolean | null = null;
let lastSentToken: string | null = null;

function isExtensionValid(): boolean {
  try {
    return Boolean(typeof chrome !== 'undefined' && chrome.runtime && !!chrome.runtime.id);
  } catch {
    return false;
  }
}

function isWaSenderSite(): boolean {
  if (typeof document === 'undefined') return false;
  // STRICT: Only recognize official WA Sender application via explicit meta tag
  const meta = document.querySelector('meta[name="wasender-app"]');
  if (meta && meta.getAttribute('content') === 'wasender') {
    return true;
  }
  return false;
}

export async function syncFromWeb() {
  if (!isExtensionValid() || !chrome.runtime?.sendMessage) {
    return;
  }

  if (!isWaSenderSite()) {
    return;
  }

  const metaApp = document.querySelector('meta[name="wasender-app"]');
  if (!metaApp) {
    return;
  }

  const origin = typeof window !== 'undefined' && window.location ? window.location.origin : '';
  const apiUrl = metaApp.getAttribute('data-api-url') || (origin ? `${origin}/api/extension` : '');

  // Guard: Never sync or overwrite API URL from unrelated subdomains (e.g. wallet.*)
  const hostname = typeof window !== 'undefined' ? window.location.hostname.toLowerCase() : '';
  if (
    !hostname.includes('sirkelta') &&
    !hostname.includes('wasender') &&
    hostname !== 'localhost' &&
    hostname !== '127.0.0.1' &&
    !hostname.endsWith('.test')
  ) {
    return;
  }

  try {
    const res = await fetch('/extension-auth/sync?force_takeover=1', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (res.ok) {
      const result = await res.json();
      if (result.authenticated && result.data) {
        const token = result.data.token;
        if (lastSentAuthStatus !== true || lastSentToken !== token) {
          lastSentAuthStatus = true;
          lastSentToken = token;
          try {
            if (isExtensionValid()) {
              chrome.runtime.sendMessage({
                type: 'SYNC_WEB_AUTH',
                payload: {
                  authenticated: true,
                  data: result.data,
                  origin,
                  apiUrl,
                },
              }, () => {
                if (chrome.runtime?.lastError) { /* ignore */ }
              });
            }
          } catch {}
        }
      } else {
        if (lastSentAuthStatus !== false) {
          lastSentAuthStatus = false;
          lastSentToken = null;
          try {
            if (isExtensionValid()) {
              chrome.runtime.sendMessage({
                type: 'SYNC_WEB_AUTH',
                payload: {
                  authenticated: false,
                  origin,
                  apiUrl,
                },
              }, () => {
                if (chrome.runtime?.lastError) { /* ignore */ }
              });
            }
          } catch {}
        }
      }
    } else if (res.status === 401) {
      if (lastSentAuthStatus !== false) {
        lastSentAuthStatus = false;
        lastSentToken = null;
        try {
          if (isExtensionValid()) {
            chrome.runtime.sendMessage({
              type: 'SYNC_WEB_AUTH',
              payload: {
                authenticated: false,
                origin,
                apiUrl,
              },
            }, () => {
              if (chrome.runtime?.lastError) { /* ignore */ }
            });
          }
        } catch {}
      }
    }
  } catch (e) {
    // Silent fail on network/unreachable host
  }
}

if (typeof document !== 'undefined') {
  if (isWaSenderSite()) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        syncFromWeb();
      });
    } else {
      syncFromWeb();
    }

    // Hook into Inertia lifecycle events for instant sync
    document.addEventListener('inertia:finish', () => syncFromWeb());
    document.addEventListener('inertia:navigate', () => syncFromWeb());

    // Detect immediate logout actions
    document.addEventListener('click', (e) => {
      const target = (e.target as HTMLElement)?.closest('a, button, form');
      if (target) {
        const text = (target.textContent || '').toLowerCase();
        const action = (target.getAttribute('href') || target.getAttribute('action') || '').toLowerCase();
        if (action.includes('/logout') || text.includes('logout') || text.includes('keluar')) {
          const origin = typeof window !== 'undefined' && window.location ? window.location.origin : '';
          const metaApp = document.querySelector('meta[name="wasender-app"]');
          const apiUrl = metaApp?.getAttribute('data-api-url') || (origin ? `${origin}/api/extension` : '');
          try {
            if (isExtensionValid()) {
              chrome.runtime.sendMessage({
                type: 'SYNC_WEB_AUTH',
                payload: { authenticated: false, origin, apiUrl }
              }, () => {
                if (chrome.runtime?.lastError) { /* ignore */ }
              });
            }
          } catch {}
          setTimeout(syncFromWeb, 600);
        }
      }
    });

    // Check on form submissions (like login/logout)
    document.addEventListener('submit', () => {
      setTimeout(syncFromWeb, 500);
    });

    // Check periodically every 20s while browsing the dashboard
    setInterval(() => {
      if (!isExtensionValid()) return;
      syncFromWeb();
    }, 20000);

    // Listen for extension logout command to invalidate web dashboard session simultaneously
    if (typeof chrome !== 'undefined' && chrome?.runtime?.onMessage) {
      chrome.runtime.onMessage.addListener((message) => {
        if (message?.type === 'LOGOUT_WEB_SESSION') {
          fetch('/extension-auth/logout', { method: 'POST', credentials: 'same-origin' }).finally(() => {
            if (typeof window !== 'undefined' && window.location) {
              window.location.reload();
            }
          });
        }
      });
    }
  }
}

