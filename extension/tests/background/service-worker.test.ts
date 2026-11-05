import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('service-worker IPC listener', () => {
  let messageListener: Function;
  let mockStorage: Record<string, any>;

  beforeEach(async () => {
    vi.resetModules();
    mockStorage = {};

    global.chrome = {
      storage: {
        local: {
          get: vi.fn((keys, cb) => {
            const res = cb ? cb(mockStorage) : Promise.resolve(mockStorage);
            return res;
          }),
          set: vi.fn((items, cb) => {
            Object.assign(mockStorage, items);
            if (cb) cb();
            return Promise.resolve();
          }),
          remove: vi.fn((keys, cb) => {
            const arr = Array.isArray(keys) ? keys : [keys];
            arr.forEach((k) => delete mockStorage[k]);
            if (cb) cb();
            return Promise.resolve();
          })
        }
      },
      tabs: {
        query: vi.fn((info, cb) => {
          const tabList = [{ id: 42, url: 'https://web.whatsapp.com' }];
          if (cb) cb(tabList);
          return Promise.resolve(tabList);
        }),
        update: vi.fn(),
        create: vi.fn(),
        sendMessage: vi.fn((tabId, msg, cb) => {
          if (cb) cb({ received: true });
        })
      },
      runtime: {
        id: 'test-service-worker-id',
        onMessage: {
          addListener: vi.fn((listener) => {
            messageListener = listener;
          })
        }
      }
    } as any;

    await import('../../src/background/service-worker');
  });

  it('registers an onMessage listener', () => {
    expect(global.chrome.runtime.onMessage.addListener).toHaveBeenCalled();
    expect(typeof messageListener).toBe('function');
  });

  it('handles START_QUEUE and returns success', async () => {
    const sendResponse = vi.fn();
    const keepOpen = messageListener(
      {
        type: 'START_QUEUE',
        payload: {
          contacts: [{ id: '1', phone: '628123', name: 'User 1', status: 'PENDING' }],
          template: 'Hello',
          settings: { minDelaySec: 1, maxDelaySec: 2, minTypingSpeedMs: 50, maxTypingSpeedMs: 100 }
        }
      },
      {},
      sendResponse
    );

    expect(keepOpen).toBe(true);
    // Wait microtasks for async IIFE inside listener
    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith({ success: true });
  });

  it('handles GET_QUEUE_STATE', async () => {
    const sendResponse = vi.fn();
    messageListener({ type: 'GET_QUEUE_STATE' }, {}, sendResponse);

    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith(
      expect.objectContaining({
        state: expect.objectContaining({
          runStatus: expect.any(String)
        })
      })
    );
  });

  it('handles PAUSE_QUEUE, RESUME_QUEUE, STOP_QUEUE', async () => {
    const sendResponse = vi.fn();

    messageListener({ type: 'PAUSE_QUEUE' }, {}, sendResponse);
    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenLastCalledWith({ success: true });

    messageListener({ type: 'RESUME_QUEUE' }, {}, sendResponse);
    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenLastCalledWith({ success: true });

    messageListener({ type: 'STOP_QUEUE' }, {}, sendResponse);
    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenLastCalledWith({ success: true });
  });

  it('handles CONTENT_RESULT and updates contact status', async () => {
    const sendResponse = vi.fn();

    // Start queue first
    messageListener(
      {
        type: 'START_QUEUE',
        payload: {
          contacts: [{ id: '1', phone: '628123', name: 'User 1', status: 'PENDING' }],
          template: 'Hello',
          settings: { minDelaySec: 0, maxDelaySec: 0, minTypingSpeedMs: 50, maxTypingSpeedMs: 100 }
        }
      },
      {},
      vi.fn()
    );
    await new Promise((r) => setTimeout(r, 50));

    messageListener(
      {
        type: 'CONTENT_RESULT',
        payload: { phone: '628123', status: 'SUCCESS' }
      },
      {},
      sendResponse
    );
    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith({ success: true });
  });

  it('handles OPEN_TAB and calls chrome.tabs.create', async () => {
    const sendResponse = vi.fn();
    messageListener(
      {
        type: 'OPEN_TAB',
        payload: { url: 'http://wasender.test/subscription' }
      },
      {},
      sendResponse
    );

    await new Promise((r) => setTimeout(r, 50));
    expect(global.chrome.tabs.create).toHaveBeenCalledWith({
      url: 'http://wasender.test/subscription'
    });
    expect(sendResponse).toHaveBeenCalledWith({ success: true });
  });

  it('handles unknown message type with error', async () => {
    const sendResponse = vi.fn();
    messageListener({ type: 'UNKNOWN_ACTION' } as any, {}, sendResponse);

    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith({ error: 'Unknown message type' });
  });

  it('catches thrown exceptions and replies with error message', async () => {
    const sendResponse = vi.fn();
    // Passing malformed payload that triggers an error when accessed
    messageListener(
      {
        type: 'START_QUEUE',
        payload: null as any
      },
      {},
      sendResponse
    );

    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith(
      expect.objectContaining({
        error: expect.any(String)
      })
    );
  });

  it('rejects SYNC_WEB_AUTH from wallet domains and purges storage key', async () => {
    mockStorage['wa_sender_custom_api_url'] = 'https://wallet.sirkelta.my.id/api/extension';
    const sendResponse = vi.fn();

    messageListener(
      {
        type: 'SYNC_WEB_AUTH',
        payload: {
          authenticated: true,
          apiUrl: 'https://wallet.sirkelta.my.id/api/extension',
          origin: 'https://wallet.sirkelta.my.id',
          data: { token: 'bad-token', user: { id: 99 } }
        }
      },
      {},
      sendResponse
    );

    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith({ success: true });
    expect(mockStorage['wa_sender_custom_api_url']).toBeUndefined();
    expect(mockStorage['wa_sender_auth_session']).toBeUndefined();
  });

  it('accepts SYNC_WEB_AUTH from legitimate wasender domain', async () => {
    const sendResponse = vi.fn();

    messageListener(
      {
        type: 'SYNC_WEB_AUTH',
        payload: {
          authenticated: true,
          apiUrl: 'https://wasender.sirkelta.my.id/api/extension',
          origin: 'https://wasender.sirkelta.my.id',
          data: { token: 'good-token', user: { id: 1 } }
        }
      },
      {},
      sendResponse
    );

    await new Promise((r) => setTimeout(r, 50));
    expect(sendResponse).toHaveBeenCalledWith({ success: true });
    expect(mockStorage['wa_sender_custom_api_url']).toBe('https://wasender.sirkelta.my.id/api/extension');
    expect(mockStorage['wa_sender_auth_session']?.token).toBe('good-token');
  });
});
