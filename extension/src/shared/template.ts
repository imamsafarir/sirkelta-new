import { ContactItem } from './types';

/**
 * Generate a clean alphanumeric random code (avoiding 0/O and 1/I).
 */
export function generateRandomCode(length: number = 5): string {
  const pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += pool.charAt(Math.floor(Math.random() * pool.length));
  }
  return result;
}

/**
 * Check if text contains spintax / random options pattern.
 */
export function hasSpintaxPattern(text: string): boolean {
  return /(\[[^\]]+[\/|][^\]]+\]|\{(?!nama\}|phone\}|nomor\}|kode_acak\})[^\}]+[\/|][^\}]+\})/.test(text);
}

/**
 * Resolve spintax patterns like [selamat pagi/pagi/assalamualaikum/halo]
 * or {opsi 1|opsi 2}.
 */
export function processSpintax(text: string): string {
  if (!text) return '';

  // 1. Process brackets [opt1/opt2/opt3] or [opt1|opt2|opt3]
  let result = text.replace(/\[([^\]]+)\]/g, (match, inner: string) => {
    if (inner.includes('/') || inner.includes('|')) {
      const delimiter = inner.includes('/') ? '/' : '|';
      const choices = inner
        .split(delimiter)
        .map((c) => c.trim())
        .filter((c) => c.length > 0);
      if (choices.length > 0) {
        return choices[Math.floor(Math.random() * choices.length)];
      }
    }
    return match;
  });

  // 2. Process curly braces {opt1|opt2} or {opt1/opt2} excluding reserved variables
  result = result.replace(/\{([^{}]+)\}/g, (match, inner: string) => {
    const lower = inner.toLowerCase();
    if (lower === 'nama' || lower === 'phone' || lower === 'nomor' || lower === 'kode_acak') {
      return match;
    }
    if (inner.includes('/') || inner.includes('|')) {
      const delimiter = inner.includes('/') ? '/' : '|';
      const choices = inner
        .split(delimiter)
        .map((c) => c.trim())
        .filter((c) => c.length > 0);
      if (choices.length > 0) {
        return choices[Math.floor(Math.random() * choices.length)];
      }
    }
    return match;
  });

  return result;
}

export interface RenderTemplateOptions {
  appendRandomCode?: boolean;
}

export function renderTemplate(
  template: string,
  contact: ContactItem,
  fallbackName: string = 'Kak/Bapak/Ibu',
  options?: RenderTemplateOptions
): string {
  const trimmed = contact.name?.trim();
  const finalName = trimmed ? trimmed : fallbackName;
  const phone = contact.phone || '';

  // 1. Replace variables
  let output = template
    .replace(/\{nama\}/gi, finalName)
    .replace(/\{nomor\}/gi, phone)
    .replace(/\{phone\}/gi, phone);

  // 2. Resolve random spintax variations
  output = processSpintax(output);

  // 3. Handle random code
  const randomCode = generateRandomCode(5);
  if (output.includes('{kode_acak}')) {
    output = output.replace(/\{kode_acak\}/gi, `#${randomCode}`);
  } else if (options?.appendRandomCode) {
    output = `${output.trimEnd()}\n\n#${randomCode}`;
  }

  return output;
}
