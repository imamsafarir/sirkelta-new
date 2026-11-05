import { QueueManager } from './queue-manager';
import { IPCMessage } from '../shared/types';
import {
  directHeartbeatApi,
  directRecordSentApi,
  directLoginApi,
  directLogoutApi,
  directValidateBatchApi,
  directGetContactsApi,
  directSaveContactsApi,
  directGetTemplatesApi,
  directCheckWebSessionApi,
  getStoredAuth,
  saveStoredAuth,
  clearStoredAuth,
  setApiBaseUrl,
  isManualLoggedOut,
  setManualLoggedOut,
  getApiBaseUrl,
  getWebBaseUrl
} from '../shared/api';

const queueManager = new QueueManager();

// Setup heartbeat alarm (every 1 minute)
if (chrome?.alarms) {
  try {
    chrome.alarms.create('EXTENSION_HEARTBEAT', { periodInMinutes: 1 });
    chrome.alarms.onAlarm.addListener((alarm) => {
      if (alarm.name === 'EXTENSION_HEARTBEAT') {
        directHeartbeatApi().catch(() => {});
      }
    });
  } catch {}
}

async function purgeWebCookies() {
  if (typeof chrome !== 'undefined' && chrome?.cookies?.getAll) {
    try {
      const apiBase = await getApiBaseUrl();
      const webBase = getWebBaseUrl(apiBase);
      const urlsToClean = [webBase, 'http://localhost:8000', 'http://127.0.0.1:8000', 'http://sirkelta-new.test', 'https://sirkelta-new.test'];

      for (const u of urlsToClean) {
        chrome.cookies.getAll({ url: u }, (cookies) => {
          if (chrome.runtime?.lastError || !cookies) return;
          for (const c of cookies) {
            if (c.name.includes('laravel_session') || c.name.includes('remember_web') || c.name.includes('XSRF-TOKEN')) {
              const protocol = c.secure ? 'https://' : 'http://';
              const domain = c.domain.replace(/^\./, '');
              const cookieUrl = `${protocol}${domain}${c.path || '/'}`;
              chrome.cookies.remove({ url: cookieUrl, name: c.name }, () => {});
            }
          }
        });
      }
    } catch {
      // ignore
    }
  }
}

// Helper to broadcast auth state changes to all extension and dashboard tabs
function broadcastAuthChanged(isLogout = false) {
  if (chrome?.tabs?.query) {
    try {
      chrome.tabs.query({}, (tabs) => {
        if (chrome.runtime?.lastError) return;
        for (const tab of tabs || []) {
          if (tab.id && tab.url) {
            try {
              if (tab.url.includes('web.whatsapp.com')) {
                chrome.tabs.sendMessage(tab.id, { type: 'AUTH_CHANGED' }, () => {
                  if (chrome.runtime?.lastError) { /* ignore */ }
                });
              } else if (isLogout && (tab.url.includes('wasender') || tab.url.includes('sirkelta') || tab.url.includes('localhost') || tab.url.includes('127.0.0.1'))) {
                chrome.tabs.sendMessage(tab.id, { type: 'LOGOUT_WEB_SESSION' }, () => {
                  if (chrome.runtime?.lastError) { /* ignore */ }
                });
              }
            } catch {
              // ignore
            }
          }
        }
      });
    } catch {
      // ignore
    }
  }
}

chrome.runtime.onMessage.addListener((message: IPCMessage, sender, sendResponse) => {
  (async () => {
    try {
      if (!message || !message.type) {
        sendResponse({ error: 'Invalid message' });
        return;
      }
      switch (message.type) {
        case 'START_QUEUE':
          await queueManager.startQueue(
            message.payload.contacts,
            message.payload.template,
            message.payload.settings,
            message.payload.appendRandomCode,
            message.payload.attachments
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

          // If message was successfully sent, report to backend to track daily quota
          if (message.payload.status === 'SUCCESS') {
            await directRecordSentApi(Boolean(message.payload.hasAttachment));
          }

          sendResponse({ success: true });
          break;

        case 'API_LOGIN':
          const loginRes = await directLoginApi(message.payload.email, message.payload.password, message.payload?.forceTakeover);
          if (loginRes.success) {
            await setManualLoggedOut(false);
          }
          sendResponse(loginRes);
          break;

        case 'API_LOGOUT':
          await purgeWebCookies();
          const logoutRes = await directLogoutApi();
          broadcastAuthChanged(true);
          sendResponse(logoutRes);
          break;

        case 'API_HEARTBEAT':
          const hbRes = await directHeartbeatApi();
          sendResponse(hbRes);
          break;

        case 'API_VALIDATE_BATCH':
          const valRes = await directValidateBatchApi(message.payload.count, message.payload.filesCount);
          sendResponse(valRes);
          break;

        case 'API_RECORD_SENT':
          const quota = await directRecordSentApi(message.payload.hasAttachment);
          sendResponse({ quota });
          break;

        case 'API_GET_CONTACTS':
          const contactsRes = await directGetContactsApi();
          sendResponse(contactsRes);
          break;

        case 'API_SAVE_CONTACTS':
          const saveRes = await directSaveContactsApi(message.payload.contacts, message.payload.groupName);
          sendResponse(saveRes);
          break;

        case 'API_GET_TEMPLATES':
          const templatesRes = await directGetTemplatesApi();
          sendResponse(templatesRes);
          break;

        case 'OPEN_TAB':
          if (message.payload?.url && chrome?.tabs?.create) {
            chrome.tabs.create({ url: message.payload.url });
            sendResponse({ success: true });
          } else {
            sendResponse({ success: false, error: 'No url or tabs API unavailable' });
          }
          break;

        case 'SYNC_WEB_AUTH':
          const targetUrl = message.payload.apiUrl || (message.payload.origin ? `${message.payload.origin}/api/extension` : '');
          const isBlacklisted = targetUrl.toLowerCase().includes('wallet');

          if (isBlacklisted) {
            // Purge poisoned URL
            await setApiBaseUrl('');
          } else if (targetUrl && message.payload.authenticated) {
            await setApiBaseUrl(targetUrl);
          }

          if (message.payload.authenticated && message.payload.data && !isBlacklisted) {
            const isManual = await isManualLoggedOut();
            if (isManual) {
              // User explicitly clicked "Keluar" on extension; block auto-takeover / background login
              sendResponse({ success: true, ignored: true });
              break;
            }

            const curAuth = await getStoredAuth();
            if (curAuth?.token !== message.payload.data.token || curAuth?.user?.id !== message.payload.data.user.id) {
              await saveStoredAuth(message.payload.data);
              broadcastAuthChanged();
            }
          } else if (!isBlacklisted) {
            const curAuth = await getStoredAuth();
            if (curAuth !== null) {
              await clearStoredAuth();
              broadcastAuthChanged();
            }
          }
          sendResponse({ success: true });
          break;

        case 'CHECK_WEB_SESSION':
          const syncRes = await directCheckWebSessionApi(message.payload?.forceTakeover, message.payload?.ignoreManualLogout);
          sendResponse(syncRes);
          break;

        default:
          sendResponse({ error: 'Unknown message type' });
      }
    } catch (err) {
      sendResponse({ error: (err as Error).message });
    }
  })().catch((err) => {
    try {
      sendResponse({ error: String(err) });
    } catch {}
  });
  return true; // Keep message channel open for async response
});
