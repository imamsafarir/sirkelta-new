// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import * as sleeper from '../../src/shared/sleeper';
import { checkAndProcessQueue } from '../../src/content/content-script';

describe('Content Script Queue Processor', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();

    // Mock sleeper.sleep to resolve immediately
    vi.spyOn(sleeper, 'sleep').mockResolvedValue();

    // Mock chrome.runtime
    (global as any).chrome = {
      runtime: {
        sendMessage: vi.fn()
      }
    };

    // Mock document.execCommand
    document.execCommand = vi.fn().mockReturnValue(true);
  });

  afterEach(() => {
    document.body.innerHTML = '';
    delete (global as any).chrome;
    vi.restoreAllMocks();
  });

  it('does nothing if url does not contain phone parameter', async () => {
    delete (window as any).location;
    (window as any).location = new URL('https://web.whatsapp.com');

    await checkAndProcessQueue();

    expect(chrome.runtime.sendMessage).not.toHaveBeenCalled();
  });

  it('does not send result if runStatus is not RUNNING', async () => {
    delete (window as any).location;
    (window as any).location = new URL('https://web.whatsapp.com/send?phone=6281234567890');

    (chrome.runtime.sendMessage as any).mockImplementation((msg: any, callback: any) => {
      if (msg.type === 'GET_QUEUE_STATE') {
        callback({
          state: {
            runStatus: 'PAUSED',
            currentIndex: 0,
            contacts: [{ phone: '6281234567890', name: 'Budi' }],
            template: 'Halo',
            settings: { minTypingSpeedMs: 1, maxTypingSpeedMs: 2 }
          }
        });
      }
    });

    await checkAndProcessQueue();

    expect(chrome.runtime.sendMessage).toHaveBeenCalledWith(
      { type: 'GET_QUEUE_STATE' },
      expect.any(Function)
    );
    expect(chrome.runtime.sendMessage).not.toHaveBeenCalledWith(
      expect.objectContaining({ type: 'CONTENT_RESULT' })
    );
  });

  it('detects invalid phone dialog and dispatches FAILED_INVALID', async () => {
    delete (window as any).location;
    (window as any).location = new URL('https://web.whatsapp.com/send?phone=6281234567890');

    const dialog = document.createElement('div');
    dialog.setAttribute('role', 'dialog');
    dialog.textContent = 'Nomor telepon yang dibagikan melalui tautan tidak valid.';
    const okBtn = document.createElement('button');
    dialog.appendChild(okBtn);
    document.body.appendChild(dialog);

    const okSpy = vi.spyOn(okBtn, 'click');

    (chrome.runtime.sendMessage as any).mockImplementation((msg: any, callback: any) => {
      if (msg.type === 'GET_QUEUE_STATE') {
        callback({
          state: {
            runStatus: 'RUNNING',
            currentIndex: 0,
            contacts: [{ id: '1', phone: '6281234567890', name: 'Budi', status: 'SENDING' }],
            template: 'Halo {{name}}',
            settings: { minTypingSpeedMs: 1, maxTypingSpeedMs: 2 }
          }
        });
      }
    });

    await checkAndProcessQueue();

    expect(okSpy).toHaveBeenCalled();
    expect(chrome.runtime.sendMessage).toHaveBeenCalledWith({
      type: 'CONTENT_RESULT',
      payload: {
        phone: '6281234567890',
        status: 'FAILED_INVALID',
        errorMessage: 'Nomor tidak terdaftar di WhatsApp'
      }
    });
  });

  it('types message, clicks send, and dispatches SUCCESS when chat input is ready', async () => {
    delete (window as any).location;
    (window as any).location = new URL('https://web.whatsapp.com/send?phone=6281234567890');

    // Create footer and input element matching CHAT_INPUT_SELECTOR
    const footer = document.createElement('footer');
    const input = document.createElement('div');
    input.setAttribute('contenteditable', 'true');
    input.setAttribute('role', 'textbox');
    footer.appendChild(input);

    const sendBtn = document.createElement('button');
    const sendIcon = document.createElement('span');
    sendIcon.setAttribute('data-icon', 'send');
    sendBtn.appendChild(sendIcon);
    footer.appendChild(sendBtn);

    document.body.appendChild(footer);

    const sendSpy = vi.spyOn(sendBtn, 'click');

    (chrome.runtime.sendMessage as any).mockImplementation((msg: any, callback: any) => {
      if (msg.type === 'GET_QUEUE_STATE') {
        callback({
          state: {
            runStatus: 'RUNNING',
            currentIndex: 0,
            contacts: [{ id: '1', phone: '6281234567890', name: 'Budi', status: 'SENDING' }],
            template: 'Halo {{name}}',
            settings: { minTypingSpeedMs: 1, maxTypingSpeedMs: 2 }
          }
        });
      }
    });

    await checkAndProcessQueue();

    expect(sendSpy).toHaveBeenCalled();
    expect(chrome.runtime.sendMessage).toHaveBeenCalledWith({
      type: 'CONTENT_RESULT',
      payload: {
        phone: '6281234567890',
        status: 'SUCCESS'
      }
    });
  });

  it('prevents concurrent execution when already processing', async () => {
    delete (window as any).location;
    (window as any).location = new URL('https://web.whatsapp.com/send?phone=6281234567890');

    let resolveState: any;
    (chrome.runtime.sendMessage as any).mockImplementation((msg: any, callback: any) => {
      if (msg.type === 'GET_QUEUE_STATE') {
        resolveState = () => {
          callback({
            state: {
              runStatus: 'RUNNING',
              currentIndex: 0,
              contacts: [{ id: '1', phone: '6281234567890', name: 'Budi', status: 'SENDING' }],
              template: 'Halo {nama}',
              settings: { minTypingSpeedMs: 1, maxTypingSpeedMs: 2 }
            }
          });
        };
      }
    });

    const run1 = checkAndProcessQueue();
    const run2 = checkAndProcessQueue();

    resolveState();
    await Promise.all([run1, run2]);

    const getQueueStateCalls = (chrome.runtime.sendMessage as any).mock.calls.filter(
      (call: any[]) => call[0]?.type === 'GET_QUEUE_STATE'
    );
    expect(getQueueStateCalls).toHaveLength(1);
  });

  describe('handleNavigateAndSend', () => {
    it('types and sends message when chat is already open', async () => {
      const { handleNavigateAndSend } = await import('../../src/content/content-script');

      // Setup #main header with contact phone
      const main = document.createElement('div');
      main.id = 'main';
      const header = document.createElement('header');
      header.textContent = '+62 812-3456-7890';
      main.appendChild(header);

      // Setup chat input
      const footer = document.createElement('footer');
      const input = document.createElement('div');
      input.setAttribute('contenteditable', 'true');
      input.setAttribute('role', 'textbox');
      footer.appendChild(input);
      main.appendChild(footer);

      // Setup send button
      const sendBtn = document.createElement('button');
      const sendIcon = document.createElement('span');
      sendIcon.setAttribute('data-icon', 'send');
      sendBtn.appendChild(sendIcon);
      const clickSpy = vi.spyOn(sendBtn, 'click');
      footer.appendChild(sendBtn);

      document.body.appendChild(main);

      await handleNavigateAndSend({
        contact: { id: '1', phone: '6281234567890', name: 'Budi', status: 'SENDING' },
        template: 'Halo {nama}',
        settings: { minDelaySec: 1, maxDelaySec: 2, minTypingSpeedMs: 1, maxTypingSpeedMs: 5 }
      });

      expect(clickSpy).toHaveBeenCalled();
      expect(chrome.runtime.sendMessage).toHaveBeenCalledWith({
        type: 'CONTENT_RESULT',
        payload: {
          phone: '6281234567890',
          status: 'SUCCESS'
        }
      });
    });
  });
});
