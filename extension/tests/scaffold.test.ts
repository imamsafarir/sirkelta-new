// tests/scaffold.test.ts
import { describe, it, expect } from 'vitest';
import manifest from '../src/manifest.json';

describe('Manifest V3 Configuration', () => {
  it('should have valid Manifest V3 structure', () => {
    expect(manifest.manifest_version).toBe(3);
    expect(manifest.name).toBe('Sirkelta WaSender');
    expect(manifest.version).toBe('1.0.0');
    expect(manifest.permissions).toContain('storage');
    expect(manifest.permissions).toContain('tabs');
    expect(manifest.host_permissions).toContain('https://web.whatsapp.com/*');
    expect(manifest.action.default_popup).toBe('src/popup/index.html');
    expect(manifest.background.service_worker).toBe('src/background/service-worker.ts');
  });
});
