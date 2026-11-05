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
        query: vi.fn((info, cb) => {
          const tabList = [{ id: 99, url: 'https://web.whatsapp.com' }];
          if (cb) cb(tabList);
          return Promise.resolve(tabList);
        }),
        update: vi.fn(),
        create: vi.fn((info, cb) => {
          const tab = { id: 100, url: info.url };
          if (cb) cb(tab);
          return Promise.resolve(tab);
        }),
        sendMessage: vi.fn((tabId, msg, cb) => {
          if (cb) cb({ received: true });
        })
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
    expect(global.chrome.tabs.sendMessage).toHaveBeenCalledWith(
      99,
      expect.objectContaining({
        type: 'NAVIGATE_AND_SEND',
        payload: expect.objectContaining({
          contact: contacts[0]
        })
      }),
      expect.any(Function)
    );
  });

  it('pauses and stops queue correctly', async () => {
    await manager.pauseQueue();
    let state = await manager.getState();
    expect(state.runStatus).toBe('PAUSED');

    await manager.stopQueue();
    state = await manager.getState();
    expect(state.runStatus).toBe('IDLE');
  });

  it('resumes paused queue', async () => {
    const contacts: ContactItem[] = [
      { id: '1', phone: '628123', name: 'A', status: 'PENDING' }
    ];
    const settings: SenderSettings = {
      minDelaySec: 5,
      maxDelaySec: 10,
      minTypingSpeedMs: 50,
      maxTypingSpeedMs: 120
    };

    await manager.startQueue(contacts, 'Halo {nama}', settings);
    await manager.pauseQueue();
    let state = await manager.getState();
    expect(state.runStatus).toBe('PAUSED');

    await manager.resumeQueue();
    state = await manager.getState();
    expect(state.runStatus).toBe('RUNNING');
  });

  it('updates contact status and advances index or marks completed', async () => {
    const contacts: ContactItem[] = [
      { id: '1', phone: '628123', name: 'A', status: 'PENDING' }
    ];
    const settings: SenderSettings = {
      minDelaySec: 0,
      maxDelaySec: 0,
      minTypingSpeedMs: 50,
      maxTypingSpeedMs: 120
    };

    await manager.startQueue(contacts, 'Halo {nama}', settings);
    await manager.updateContactStatus('628123', 'SUCCESS');

    const state = await manager.getState();
    expect(state.contacts[0].status).toBe('SUCCESS');
    expect(state.contacts[0].sentAt).toBeDefined();
    expect(state.runStatus).toBe('COMPLETED');
  });

  it('advances currentIndex even if queue is paused when contact status arrives', async () => {
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
    await manager.pauseQueue();

    // Contact result arrives while paused
    await manager.updateContactStatus('628123', 'SUCCESS');

    const state = await manager.getState();
    expect(state.contacts[0].status).toBe('SUCCESS');
    expect(state.currentIndex).toBe(1);
    expect(state.runStatus).toBe('PAUSED');
  });

  it('creates whatsapp tab if none exists', async () => {
    (global.chrome.tabs.query as any).mockImplementation((info: any, cb: any) => {
      if (cb) cb([]);
      return Promise.resolve([]);
    });

    const contacts: ContactItem[] = [
      { id: '1', phone: '628123', name: 'A', status: 'PENDING' }
    ];
    const settings: SenderSettings = {
      minDelaySec: 5,
      maxDelaySec: 10,
      minTypingSpeedMs: 50,
      maxTypingSpeedMs: 120
    };

    await manager.startQueue(contacts, 'Halo {nama}', settings);
    expect(global.chrome.tabs.create).toHaveBeenCalledWith(
      { url: 'https://web.whatsapp.com' },
      expect.any(Function)
    );
  });

  it('correctly targets duplicate phone numbers by current index', async () => {
    const contacts: ContactItem[] = [
      { id: '1', phone: '628123', name: 'A', status: 'PENDING' },
      { id: '2', phone: '628123', name: 'B', status: 'PENDING' }
    ];
    const settings: SenderSettings = {
      minDelaySec: 0,
      maxDelaySec: 0,
      minTypingSpeedMs: 50,
      maxTypingSpeedMs: 120
    };

    await manager.startQueue(contacts, 'Halo {nama}', settings);
    // Complete first contact
    await manager.updateContactStatus('628123', 'SUCCESS');

    let state = await manager.getState();
    expect(state.contacts[0].status).toBe('SUCCESS');
    expect(state.contacts[1].status).toBe('SENDING');
    expect(state.currentIndex).toBe(1);

    // Complete second contact with same phone
    await manager.updateContactStatus('628123', 'SUCCESS');

    state = await manager.getState();
    expect(state.contacts[0].status).toBe('SUCCESS');
    expect(state.contacts[1].status).toBe('SUCCESS');
    expect(state.runStatus).toBe('COMPLETED');
  });
});
