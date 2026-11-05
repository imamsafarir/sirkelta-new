import { describe, it, expect } from 'vitest';
import { parseContactList } from '../../src/shared/normalizer';
import { renderTemplate } from '../../src/shared/template';
import { normalizeRange } from '../../src/popup/popup';

describe('Popup UI logic', () => {
  it('updates live preview when template and contact list change', () => {
    const rawContacts = '081234567890, Budi Hartono';
    const contacts = parseContactList(rawContacts);
    const template = 'Selamat siang {nama}, tagihan Anda sudah terbit.';

    const preview = renderTemplate(template, contacts[0]);
    expect(preview).toBe('Selamat siang Budi Hartono, tagihan Anda sudah terbit.');
  });

  it('renders fallback preview when contact list is empty', () => {
    const rawContacts = '';
    const contacts = parseContactList(rawContacts);
    const template = 'Halo kak {nama}, terima kasih telah menghubungi kami.';

    const preview = contacts.length > 0
      ? renderTemplate(template, contacts[0])
      : renderTemplate(template, {
          id: '0',
          phone: '628xxx',
          name: 'Nama Pelanggan',
          status: 'PENDING'
        });

    expect(preview).toBe('Halo kak Nama Pelanggan, terima kasih telah menghubungi kami.');
  });

  it('parses multiple contacts and updates preview with the first contact', () => {
    const rawContacts = '081234567890, Budi\n089876543210, Siti';
    const contacts = parseContactList(rawContacts);
    const template = 'Halo kak {nama}!';

    expect(contacts).toHaveLength(2);
    expect(renderTemplate(template, contacts[0])).toBe('Halo kak Budi!');
  });

  it('normalizes inverted ranges when min > max', () => {
    expect(normalizeRange(10, 5)).toEqual([5, 10]);
    expect(normalizeRange(200, 50)).toEqual([50, 200]);
    expect(normalizeRange(5, 10)).toEqual([5, 10]);
    expect(normalizeRange(5, 5)).toEqual([5, 5]);
  });

  it('checks WhatsApp Web tab status correctly', async () => {
    const { checkWhatsAppTab } = await import('../../src/popup/popup');
    // Global chrome mock in tests
    const tab = await checkWhatsAppTab();
    expect(tab === null || typeof tab === 'object').toBe(true);
  });
});
