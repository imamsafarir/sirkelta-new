import { ContactItem } from './types';

export function cleanPhoneNumber(input: string): string {
  let cleaned = input.replace(/[^0-9]/g, '');
  if (cleaned.startsWith('08')) {
    cleaned = '628' + cleaned.slice(2);
  } else if (cleaned.startsWith('8')) {
    cleaned = '628' + cleaned.slice(1);
  } else if (cleaned.startsWith('0062')) {
    cleaned = '62' + cleaned.slice(4);
  }
  return cleaned;
}

export function parseContactList(rawInput: string): ContactItem[] {
  const lines = rawInput.split('\n');
  const contacts: ContactItem[] = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trim();
    if (!line) continue;

    const parts = line.split(/[,\t]/).map((p) => p.trim());
    const rawPhone = parts[0] || '';
    const name = parts.slice(1).join(' ').trim();
    const phone = cleanPhoneNumber(rawPhone);

    if (phone.length >= 8) {
      contacts.push({
        id: `contact-${Date.now()}-${i}-${Math.random().toString(36).slice(2, 6)}`,
        phone,
        name,
        status: 'PENDING'
      });
    }
  }

  return contacts;
}
