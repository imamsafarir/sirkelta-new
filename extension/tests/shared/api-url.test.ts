import { describe, it, expect, beforeEach, vi } from 'vitest';
import { getApiBaseUrl, setApiBaseUrl, DEFAULT_API_URL } from '../../src/shared/api';

describe('API URL isolation and sanitization', () => {
  let mockStorage: Record<string, any>;

  beforeEach(() => {
    vi.resetModules();
    mockStorage = {};

    global.chrome = {
      runtime: {
        id: 'test-ext-id',
        getURL: vi.fn((path) => `chrome-extension://test-ext-id/${path}`),
        lastError: null
      },
      storage: {
        local: {
          get: vi.fn((keys: string[], cb: Function) => {
            const out: Record<string, any> = {};
            keys.forEach((k) => {
              if (mockStorage[k] !== undefined) out[k] = mockStorage[k];
            });
            cb(out);
          }),
          set: vi.fn((items: Record<string, any>, cb: Function) => {
            Object.assign(mockStorage, items);
            if (cb) cb();
          }),
          remove: vi.fn((keys: string | string[], cb: Function) => {
            const arr = Array.isArray(keys) ? keys : [keys];
            arr.forEach((k) => delete mockStorage[k]);
            if (cb) cb();
          })
        }
      }
    } as any;
  });

  it('purges poisoned wallet url from storage and falls back to default/config', async () => {
    mockStorage['wa_sender_custom_api_url'] = 'https://wallet.sirkelta.my.id/api/extension';

    const url = await getApiBaseUrl();
    expect(url).not.toContain('wallet');
    expect(mockStorage['wa_sender_custom_api_url']).toBeUndefined();
  });

  it('rejects saving any URL containing wallet in setApiBaseUrl and removes it', async () => {
    mockStorage['wa_sender_custom_api_url'] = 'https://wallet.sirkelta.my.id/api/extension';

    await setApiBaseUrl('https://wallet.sirkelta.my.id/api/extension');
    expect(mockStorage['wa_sender_custom_api_url']).toBeUndefined();

    const url = await getApiBaseUrl();
    expect(url).not.toContain('wallet');
  });

  it('allows saving and retrieving a valid wasender domain URL', async () => {
    const validUrl = 'https://wasender.sirkelta.my.id/api/extension';
    await setApiBaseUrl(validUrl);

    expect(mockStorage['wa_sender_custom_api_url']).toBe(validUrl);

    const url = await getApiBaseUrl();
    expect(url).toBe(validUrl);
  });
});

