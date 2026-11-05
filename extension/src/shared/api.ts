import { AuthSessionData, QuotaData } from './types';

const STORAGE_AUTH_KEY = 'wa_sender_auth_session';
const STORAGE_DEVICE_KEY = 'wa_sender_device_id';
const STORAGE_API_URL_KEY = 'wa_sender_custom_api_url';
export const STORAGE_MANUAL_LOGOUT_KEY = 'wa_sender_manual_logout';

export const DEFAULT_API_URL = 'http://localhost:8000/api/extension';

export function isExtensionContextValid(): boolean {
  try {
    if (typeof chrome === 'undefined' || !chrome.runtime) return false;
    if ('id' in chrome.runtime) {
      return Boolean(chrome.runtime.id);
    }
    return Boolean(chrome.runtime.sendMessage || chrome.runtime.getURL);
  } catch {
    return false;
  }
}

function hasLastError(): boolean {
  try {
    return Boolean(isExtensionContextValid() && chrome.runtime?.lastError);
  } catch {
    return true;
  }
}

export async function getApiBaseUrl(): Promise<string> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) {
      resolve(DEFAULT_API_URL);
      return;
    }
    try {
      chrome.storage.local.get([STORAGE_API_URL_KEY], async (res) => {
        if (hasLastError()) {
          resolve(DEFAULT_API_URL);
          return;
        }

        const storedUrl = res?.[STORAGE_API_URL_KEY];
        if (storedUrl && typeof storedUrl === 'string') {
          if (storedUrl.toLowerCase().includes('wallet')) {
            // Purge poisoned key immediately
            try {
              chrome.storage.local.remove([STORAGE_API_URL_KEY]);
            } catch {}
          } else {
            resolve(storedUrl);
            return;
          }
        }

        // Check packaged config.json if available
        try {
          if (typeof chrome.runtime?.getURL === 'function') {
            const cfgUrl = chrome.runtime.getURL('config.json');
            const resp = await fetch(cfgUrl);
            if (resp.ok) {
              const data = await resp.json();
              if (data?.api_url && typeof data.api_url === 'string' && !data.api_url.toLowerCase().includes('wallet')) {
                resolve(data.api_url);
                return;
              }
            }
          }
        } catch {}

        resolve(DEFAULT_API_URL);
      });
    } catch {
      resolve(DEFAULT_API_URL);
    }
  });
}

export async function setApiBaseUrl(url: string): Promise<void> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) return resolve();
    if (!url || typeof url !== 'string' || url.toLowerCase().includes('wallet')) {
      // Purge storage key if invalid or blacklisted
      try {
        chrome.storage.local.remove([STORAGE_API_URL_KEY], () => resolve());
      } catch {
        resolve();
      }
      return;
    }
    try {
      chrome.storage.local.set({ [STORAGE_API_URL_KEY]: url }, () => resolve());
    } catch {
      resolve();
    }
  });
}

export async function getOrCreateDeviceId(): Promise<string> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) {
      resolve('dev-browser-standalone');
      return;
    }
    try {
      chrome.storage.local.get([STORAGE_DEVICE_KEY], (res) => {
        if (hasLastError()) {
          resolve('dev-browser-standalone');
          return;
        }
        if (res && res[STORAGE_DEVICE_KEY]) {
          resolve(res[STORAGE_DEVICE_KEY]);
        } else {
          const newId = 'dev-' + Math.random().toString(36).substring(2, 12) + '-' + Date.now().toString(36);
          try {
            chrome.storage.local.set({ [STORAGE_DEVICE_KEY]: newId }, () => {
              resolve(newId);
            });
          } catch {
            resolve(newId);
          }
        }
      });
    } catch {
      resolve('dev-browser-standalone');
    }
  });
}

export async function getStoredAuth(): Promise<AuthSessionData | null> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) {
      resolve(null);
      return;
    }
    try {
      chrome.storage.local.get([STORAGE_AUTH_KEY], (res) => {
        if (hasLastError()) {
          resolve(null);
          return;
        }
        resolve(res?.[STORAGE_AUTH_KEY] || null);
      });
    } catch {
      resolve(null);
    }
  });
}

export async function isManualLoggedOut(): Promise<boolean> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) {
      resolve(false);
      return;
    }
    try {
      chrome.storage.local.get([STORAGE_MANUAL_LOGOUT_KEY], (res) => {
        if (hasLastError()) {
          resolve(false);
          return;
        }
        resolve(res?.[STORAGE_MANUAL_LOGOUT_KEY] === true);
      });
    } catch {
      resolve(false);
    }
  });
}

export async function setManualLoggedOut(value: boolean): Promise<void> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) return resolve();
    try {
      if (value) {
        chrome.storage.local.set({ [STORAGE_MANUAL_LOGOUT_KEY]: true }, () => resolve());
      } else {
        chrome.storage.local.remove([STORAGE_MANUAL_LOGOUT_KEY], () => resolve());
      }
    } catch {
      resolve();
    }
  });
}

export async function saveStoredAuth(data: AuthSessionData): Promise<void> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) return resolve();
    try {
      chrome.storage.local.set({ [STORAGE_AUTH_KEY]: data }, () => {
        chrome.storage.local.remove([STORAGE_MANUAL_LOGOUT_KEY], () => resolve());
      });
    } catch {
      resolve();
    }
  });
}

export async function clearStoredAuth(): Promise<void> {
  return new Promise((resolve) => {
    if (!isExtensionContextValid() || !chrome.storage?.local) return resolve();
    try {
      chrome.storage.local.remove([STORAGE_AUTH_KEY], () => resolve());
    } catch {
      resolve();
    }
  });
}

function isPageContext(): boolean {
  // Returns true if running in window / content script where fetch from https can be blocked by Mixed Content or CORS
  return typeof window !== 'undefined' && isExtensionContextValid() && !!chrome.runtime?.sendMessage;
}

// DIRECT API CALLS (executed in Service Worker context or tests)
export async function directLoginApi(
  email: string,
  password: string,
  forceTakeover: boolean = false
): Promise<{ success: boolean; message: string; data?: AuthSessionData; code?: string; active_session?: { device_name: string; ip_address: string; last_heartbeat: string } }> {
  try {
    const baseUrl = await getApiBaseUrl();
    const webBaseUrl = getWebBaseUrl(baseUrl);
    const deviceId = await getOrCreateDeviceId();
    const isWin = typeof navigator !== 'undefined' && navigator.userAgent.includes('Windows');
    const deviceName = 'Chrome di ' + (isWin ? 'Windows' : 'Komputer');

    let response: Response;
    try {
      response = await fetch(`${webBaseUrl}/extension-auth/login`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          email,
          login: email,
          password,
          device_name: deviceName,
          device_id: deviceId,
          force_takeover: forceTakeover,
        }),
      });
      if (response.status === 404) {
        throw new Error('Fallback to direct api');
      }
    } catch {
      response = await fetch(`${baseUrl}/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          email,
          login: email,
          password,
          device_name: deviceName,
          device_id: deviceId,
          force_takeover: forceTakeover,
        }),
      });
    }

    const result = await response.json();

    if (!response.ok) {
      return {
        success: false,
        code: result.code || 'LOGIN_FAILED',
        message: result.message || 'Login gagal. Periksa username dan password Anda.',
        active_session: result.active_session,
      };
    }

    const authData: AuthSessionData = {
      token: result.data.token,
      user: result.data.user,
      plan: result.data.plan,
      quota: result.data.quota,
    };

    await saveStoredAuth(authData);
    await setManualLoggedOut(false);

    return {
      success: true,
      message: result.message || 'Login berhasil.',
      data: authData,
    };
  } catch (err) {
    const baseUrl = await getApiBaseUrl();
    return {
      success: false,
      code: 'NETWORK_ERROR',
      message: 'Tidak dapat terhubung ke server (' + baseUrl + '). Pastikan server Sirkelta aktif di browser.',
    };
  }
}

export async function directHeartbeatApi(): Promise<{ success: boolean; quota?: QuotaData; code?: string }> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) {
      return { success: false, code: 'UNAUTHENTICATED' };
    }

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/heartbeat`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
    });

    const result = await response.json();

    if (!response.ok) {
      if (response.status === 401 || response.status === 403) {
        await clearStoredAuth();
      }
      return { success: false, code: result.code || 'ERROR' };
    }

    if (result.quota) {
      auth.quota = result.quota;
      await saveStoredAuth(auth);
    }

    return { success: true, quota: result.quota };
  } catch {
    return { success: false, code: 'NETWORK_ERROR' };
  }
}

export async function directLogoutApi(): Promise<{ success: boolean; message: string }> {
  try {
    const auth = await getStoredAuth();
    const baseUrl = await getApiBaseUrl();
    const webBaseUrl = getWebBaseUrl(baseUrl);

    // 1. Unified Web Logout: Invalidate Laravel web session and purge session cookie
    try {
      await fetch(`${webBaseUrl}/extension-auth/logout`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...(auth?.token ? { 'Authorization': `Bearer ${auth.token}` } : {}),
        },
      });
    } catch {
      // Ignore network error on web logout
    }

    // 2. Extension session revoke
    if (auth && auth.token) {
      try {
        await fetch(`${baseUrl}/logout`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${auth.token}`,
          },
        });
      } catch {
        // Ignore network error
      }
    }
  } catch {
    // Ignore network error during logout
  } finally {
    await clearStoredAuth();
    await setManualLoggedOut(true);
  }

  return { success: true, message: 'Berhasil logout.' };
}

export async function directValidateBatchApi(count: number, filesCount = 0): Promise<{ allowed: boolean; message?: string; quota?: QuotaData; code?: string }> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) {
      return { allowed: false, code: 'UNAUTHENTICATED', message: 'Silakan login ke akun Sirkelta terlebih dahulu.' };
    }

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/validate-batch`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
      body: JSON.stringify({ count, files_count: filesCount }),
    });

    const result = await response.json();

    if (!response.ok) {
      return {
        allowed: false,
        code: result.code,
        message: result.message || 'Validasi pengiriman ditolak.',
        quota: result.quota,
      };
    }

    if (result.quota) {
      auth.quota = result.quota;
      await saveStoredAuth(auth);
    }

    return { allowed: true, quota: result.quota };
  } catch (err) {
    return {
      allowed: false,
      code: 'NETWORK_ERROR',
      message: 'Gagal menghubungi server Sirkelta untuk verifikasi kuota (' + (err as Error).message + ').',
    };
  }
}

export async function directRecordSentApi(hasAttachment = false): Promise<QuotaData | null> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) return null;

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/record-sent`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
      body: JSON.stringify({ success: true, has_attachment: hasAttachment }),
    });

    if (response.ok) {
      const result = await response.json();
      if (result.quota) {
        auth.quota = result.quota;
        await saveStoredAuth(auth);
        return result.quota;
      }
    }
  } catch {
    // Background tracking fail tolerance
  }
  return null;
}

export async function directGetContactsApi(): Promise<{ success: boolean; data?: Array<{ id: number; name: string; phone: string; group_name: string }>; message?: string }> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) {
      return { success: false, message: 'Belum login.' };
    }

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/contacts`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
    });

    const result = await response.json();
    if (response.ok && result.data) {
      return { success: true, data: result.data };
    }
    return { success: false, message: result.message || 'Gagal memuat kontak.' };
  } catch (err) {
    return { success: false, message: (err as Error).message };
  }
}

export async function directSaveContactsApi(
  contacts: Array<{ name?: string; phone: string }>,
  groupName: string
): Promise<{ success: boolean; message: string; saved_count?: number; total?: number; code?: string }> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) {
      return { success: false, code: 'UNAUTHENTICATED', message: 'Belum login.' };
    }

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/contacts`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
      body: JSON.stringify({ contacts, group_name: groupName }),
    });

    const result = await response.json();
    if (response.ok) {
      return {
        success: true,
        message: result.message || 'Kontak berhasil disimpan.',
        saved_count: result.saved_count,
        total: result.total,
      };
    }
    return {
      success: false,
      code: result.code || 'SAVE_FAILED',
      message: result.message || 'Gagal menyimpan kontak.',
    };
  } catch (err) {
    return { success: false, code: 'NETWORK_ERROR', message: (err as Error).message };
  }
}

export async function directGetTemplatesApi(): Promise<{ success: boolean; data?: Array<{ id: number; title: string; content: string; category: string; has_random_code?: boolean }>; message?: string }> {
  try {
    const auth = await getStoredAuth();
    if (!auth || !auth.token) {
      return { success: false, message: 'Belum login.' };
    }

    const baseUrl = await getApiBaseUrl();
    const response = await fetch(`${baseUrl}/templates`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${auth.token}`,
      },
    });

    const result = await response.json();
    if (response.ok && result.data) {
      return { success: true, data: result.data };
    }
    return { success: false, message: result.message || 'Gagal memuat template.' };
  } catch (err) {
    return { success: false, message: (err as Error).message };
  }
}

// PUBLIC WRAPPERS WITH AUTOMATIC SERVICE WORKER PROXYING
export async function loginApi(
  email: string,
  password: string,
  forceTakeover: boolean = false
): Promise<{ success: boolean; message: string; data?: AuthSessionData; code?: string; active_session?: { device_name: string; ip_address: string; last_heartbeat: string } }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_LOGIN', payload: { email, password, forceTakeover } }, (res) => {
          if (hasLastError() || !res) {
            resolve(directLoginApi(email, password, forceTakeover));
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directLoginApi(email, password, forceTakeover));
      }
    });
  }
  return directLoginApi(email, password, forceTakeover);
}

export async function heartbeatApi(): Promise<{ success: boolean; quota?: QuotaData; code?: string }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_HEARTBEAT' }, (res) => {
          if (hasLastError() || !res) {
            resolve(directHeartbeatApi());
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directHeartbeatApi());
      }
    });
  }
  return directHeartbeatApi();
}

export async function logoutApi(): Promise<{ success: boolean; message: string }> {
  await setManualLoggedOut(true);
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_LOGOUT' }, (res) => {
          if (hasLastError() || !res) {
            resolve(directLogoutApi());
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directLogoutApi());
      }
    });
  }
  return directLogoutApi();
}

export async function validateBatchApi(count: number, filesCount = 0): Promise<{ allowed: boolean; message?: string; quota?: QuotaData; code?: string }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_VALIDATE_BATCH', payload: { count, filesCount } }, (res) => {
          if (hasLastError() || !res) {
            resolve(directValidateBatchApi(count, filesCount));
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directValidateBatchApi(count, filesCount));
      }
    });
  }
  return directValidateBatchApi(count, filesCount);
}

export async function recordSentApi(hasAttachment = false): Promise<QuotaData | null> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_RECORD_SENT', payload: { hasAttachment } }, (res) => {
          if (hasLastError() || !res) {
            resolve(directRecordSentApi(hasAttachment));
          } else {
            resolve(res?.quota || null);
          }
        });
      } catch {
        resolve(directRecordSentApi(hasAttachment));
      }
    });
  }
  return directRecordSentApi(hasAttachment);
}

export async function getContactsApi(): Promise<{ success: boolean; data?: Array<{ id: number; name: string; phone: string; group_name: string }>; message?: string }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_GET_CONTACTS' }, (res) => {
          if (hasLastError() || !res) {
            resolve(directGetContactsApi());
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directGetContactsApi());
      }
    });
  }
  return directGetContactsApi();
}

export async function saveContactsApi(
  contacts: Array<{ name?: string; phone: string }>,
  groupName: string
): Promise<{ success: boolean; message: string; saved_count?: number; total?: number; code?: string }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_SAVE_CONTACTS', payload: { contacts, groupName } }, (res) => {
          if (hasLastError() || !res) {
            resolve(directSaveContactsApi(contacts, groupName));
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directSaveContactsApi(contacts, groupName));
      }
    });
  }
  return directSaveContactsApi(contacts, groupName);
}

export async function getTemplatesApi(): Promise<{ success: boolean; data?: Array<{ id: number; title: string; content: string; category: string; has_random_code?: boolean }>; message?: string }> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'API_GET_TEMPLATES' }, (res) => {
          if (hasLastError() || !res) {
            resolve(directGetTemplatesApi());
          } else {
            resolve(res);
          }
        });
      } catch {
        resolve(directGetTemplatesApi());
      }
    });
  }
  return directGetTemplatesApi();
}

export function getWebBaseUrl(apiBaseUrl: string): string {
  return apiBaseUrl.replace(/\/api\/extension\/?$/, '').replace(/\/api\/?$/, '');
}

export async function directCheckWebSessionApi(forceTakeover = false, ignoreManualLogout = false): Promise<{
  success: boolean;
  authenticated: boolean;
  message?: string;
  data?: AuthSessionData;
  code?: string;
  active_session?: { device_name: string; ip_address: string; last_heartbeat: string };
}> {
  try {
    if (!ignoreManualLogout) {
      const isLoggedOut = await isManualLoggedOut();
      if (isLoggedOut) {
        return {
          success: false,
          authenticated: false,
          code: 'MANUAL_LOGOUT',
          message: 'Pengguna telah logout secara manual dari ekstensi.',
        };
      }
    }

    const apiBaseUrl = await getApiBaseUrl();
    const webBaseUrl = getWebBaseUrl(apiBaseUrl);
    const deviceId = await getOrCreateDeviceId();
    const isWin = typeof navigator !== 'undefined' && navigator.userAgent.includes('Windows');
    const deviceName = 'Chrome di ' + (isWin ? 'Windows' : 'Komputer');

    const params = new URLSearchParams({
      device_id: deviceId,
      device_name: deviceName,
      force_takeover: String(forceTakeover),
    });

    const response = await fetch(`${webBaseUrl}/extension-auth/sync?${params.toString()}`, {
      method: 'GET',
      credentials: 'include',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    const result = await response.json();

    if (response.status === 401 || !result.authenticated) {
      const existing = await getStoredAuth();
      if (existing !== null) {
        await clearStoredAuth();
      }
      return {
        success: false,
        authenticated: false,
        message: result.message || 'Belum ada sesi login aktif di website.',
      };
    }

    if (response.status === 409 || result.code === 'SESSION_LOCKED') {
      return {
        success: false,
        authenticated: true,
        code: 'SESSION_LOCKED',
        message: result.message,
        active_session: result.active_session,
      };
    }

    if (!response.ok || !result.data) {
      return {
        success: false,
        authenticated: false,
        code: result.code || 'SYNC_FAILED',
        message: result.message || 'Gagal menyinkronkan sesi web.',
      };
    }

    const authData: AuthSessionData = {
      token: result.data.token,
      user: result.data.user,
      plan: result.data.plan,
      quota: result.data.quota,
    };

    await saveStoredAuth(authData);
    await setManualLoggedOut(false);

    return {
      success: true,
      authenticated: true,
      message: result.message,
      data: authData,
    };
  } catch (err) {
    return {
      success: false,
      authenticated: false,
      message: (err as Error).message || 'Koneksi ke server website terputus.',
    };
  }
}

export async function checkWebSessionApi(forceTakeover = false, ignoreManualLogout = false): Promise<{
  success: boolean;
  authenticated: boolean;
  message?: string;
  data?: AuthSessionData;
  code?: string;
  active_session?: { device_name: string; ip_address: string; last_heartbeat: string };
}> {
  if (isPageContext()) {
    return new Promise((resolve) => {
      try {
        chrome.runtime.sendMessage({ type: 'CHECK_WEB_SESSION', payload: { forceTakeover, ignoreManualLogout } }, (res) => {
          if (hasLastError()) {
            resolve({ success: false, authenticated: false, message: 'Tidak ada respon dari service worker' });
            return;
          }
          resolve(res || { success: false, authenticated: false, message: 'Tidak ada respon dari service worker' });
        });
      } catch {
        resolve({ success: false, authenticated: false, message: 'Extension context invalidated' });
      }
    });
  }
  return directCheckWebSessionApi(forceTakeover, ignoreManualLogout);
}
