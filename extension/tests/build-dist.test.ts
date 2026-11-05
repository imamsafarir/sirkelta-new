import { describe, it, expect, beforeAll } from 'vitest';
import { existsSync, readFileSync } from 'fs';
import { resolve } from 'path';
import { execSync } from 'child_process';

describe('Production Extension Bundle (dist/)', () => {
  beforeAll(() => {
    const manifestPath = resolve(__dirname, '../dist/manifest.json');
    if (!existsSync(manifestPath)) {
      execSync('npm run build', { cwd: resolve(__dirname, '..'), stdio: 'inherit' });
    }
  });

  it('contains valid manifest.json with compiled JS paths', () => {
    const manifestPath = resolve(__dirname, '../dist/manifest.json');
    expect(existsSync(manifestPath)).toBe(true);

    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    expect(manifest.manifest_version).toBe(3);
    expect(manifest.background.service_worker).toBe('src/background/service-worker.js');
    expect(manifest.content_scripts[0].js).toContain('src/content/content-script.js');
    expect(manifest.content_scripts[0].css).toContain('src/content/content-style.css');
    expect(manifest.web_accessible_resources).toBeDefined();
  });

  it('contains compiled content style CSS with light scheme and dynamic shift', () => {
    const cssPath = resolve(__dirname, '../dist/src/content/content-style.css');
    expect(existsSync(cssPath)).toBe(true);

    const css = readFileSync(cssPath, 'utf8');
    expect(css).toContain('color-scheme: light');
    expect(css).toContain('calc(100vw - 460px)');
  });

  it('contains compiled background service worker script', () => {
    const swPath = resolve(__dirname, '../dist/src/background/service-worker.js');
    expect(existsSync(swPath)).toBe(true);
  });

  it('contains compiled content script', () => {
    const csPath = resolve(__dirname, '../dist/src/content/content-script.js');
    expect(existsSync(csPath)).toBe(true);

    const content = readFileSync(csPath, 'utf8');
    expect(content).not.toMatch(/^import\s+/m);
  });

  it('contains popup index.html', () => {
    const popupPath = resolve(__dirname, '../dist/src/popup/index.html');
    expect(existsSync(popupPath)).toBe(true);
  });
});
