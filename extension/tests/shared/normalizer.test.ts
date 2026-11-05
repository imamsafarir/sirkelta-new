import { describe, it, expect } from 'vitest';
import { cleanPhoneNumber, parseContactList } from '../../src/shared/normalizer';

describe('Normalizer utilities', () => {
  it('cleans 08xx Indonesian numbers to 628xx format', () => {
    expect(cleanPhoneNumber('081234567890')).toBe('6281234567890');
    expect(cleanPhoneNumber('+62 812-3456-7890')).toBe('6281234567890');
    expect(cleanPhoneNumber('6281234567890')).toBe('6281234567890');
    expect(cleanPhoneNumber('81234567890')).toBe('6281234567890');
  });

  it('parses multiple contact lines with name and phone', () => {
    const raw = `
      081234567890, Budi Santoso
      +62 898-7654-3210, Siti
      085611122233
    `;
    const result = parseContactList(raw);
    expect(result).toHaveLength(3);
    expect(result[0]).toEqual({
      id: expect.any(String),
      phone: '6281234567890',
      name: 'Budi Santoso',
      status: 'PENDING'
    });
    expect(result[1]).toEqual({
      id: expect.any(String),
      phone: '6289876543210',
      name: 'Siti',
      status: 'PENDING'
    });
    expect(result[2]).toEqual({
      id: expect.any(String),
      phone: '6285611122233',
      name: '',
      status: 'PENDING'
    });
  });
});
