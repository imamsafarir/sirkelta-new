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

  it('handles case-insensitive placeholders like {NAMA} and {Nama}', () => {
    const contact: ContactItem = {
      id: '3',
      phone: '6281234567890',
      name: 'Dewi',
      status: 'PENDING'
    };
    const template = 'Halo {NAMA}, apa kabar {Nama} hari ini?';
    expect(renderTemplate(template, contact)).toBe('Halo Dewi, apa kabar Dewi hari ini?');
  });

  it('defensively handles undefined or whitespace-only contact name', () => {
    const contactWithUndefined = {
      id: '4',
      phone: '6281234567890',
      status: 'PENDING'
    } as unknown as ContactItem;
    expect(renderTemplate('Halo {nama}', contactWithUndefined)).toBe('Halo Kak/Bapak/Ibu');

    const contactWithWhitespace = {
      id: '5',
      phone: '6281234567890',
      name: '   ',
      status: 'PENDING'
    };
    expect(renderTemplate('Halo {nama}', contactWithWhitespace)).toBe('Halo Kak/Bapak/Ibu');
  });

  it('replaces {nomor} and {phone} with contact phone number', () => {
    const contact: ContactItem = {
      id: '6',
      phone: '6281234567890',
      name: 'Rudi',
      status: 'PENDING'
    };
    expect(renderTemplate('Halo {nama}, nomor Anda adalah {nomor}', contact)).toBe('Halo Rudi, nomor Anda adalah 6281234567890');
    expect(renderTemplate('Phone: {phone}', contact)).toBe('Phone: 6281234567890');
  });
});
