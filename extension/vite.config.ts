import { defineConfig } from 'vite';
import { resolve } from 'path';
import { existsSync, mkdirSync, readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { build as esbuildBuild } from 'esbuild';
import JavaScriptObfuscator from 'javascript-obfuscator';

function getAllJsFiles(dir: string, fileList: string[] = []): string[] {
  if (!existsSync(dir)) return fileList;
  const files = readdirSync(dir);
  for (const file of files) {
    const fullPath = resolve(dir, file);
    if (statSync(fullPath).isDirectory()) {
      getAllJsFiles(fullPath, fileList);
    } else if (file.endsWith('.js')) {
      fileList.push(fullPath);
    }
  }
  return fileList;
}

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        popup: resolve(__dirname, 'src/popup/index.html'),
        background: resolve(__dirname, 'src/background/service-worker.ts')
      },
      output: {
        entryFileNames: (chunkInfo) => {
          if (chunkInfo.name === 'background') return 'src/background/service-worker.js';
          return 'assets/[name]-[hash].js';
        },
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash].[ext]'
      }
    }
  },
  plugins: [
    {
      name: 'build-content-script-and-manifest',
      async closeBundle() {
        if (!existsSync('dist')) {
          mkdirSync('dist', { recursive: true });
        }

        // 1. Bundle content scripts as standalone IIFE scripts (Chrome MV3 classic script compatibility)
        mkdirSync(resolve(__dirname, 'dist/src/content'), { recursive: true });
        await esbuildBuild({
          entryPoints: [resolve(__dirname, 'src/content/content-script.ts')],
          bundle: true,
          outfile: resolve(__dirname, 'dist/src/content/content-script.js'),
          format: 'iife',
          target: 'es2020'
        });

        await esbuildBuild({
          entryPoints: [resolve(__dirname, 'src/content/web-auth-bridge.ts')],
          bundle: true,
          outfile: resolve(__dirname, 'dist/src/content/web-auth-bridge.js'),
          format: 'iife',
          target: 'es2020'
        });

        // Copy content-style.css to dist
        const contentCssSrc = resolve(__dirname, 'src/content/content-style.css');
        if (existsSync(contentCssSrc)) {
          writeFileSync(resolve(__dirname, 'dist/src/content/content-style.css'), readFileSync(contentCssSrc));
        }

        // 2. Parse and mutate manifest.json
        const rawManifest = readFileSync(resolve(__dirname, 'src/manifest.json'), 'utf-8');
        const manifest = JSON.parse(rawManifest);
        if (manifest.background && manifest.background.service_worker) {
          manifest.background.service_worker = manifest.background.service_worker.replace(/\.ts$/, '.js');
        }
        if (manifest.content_scripts && Array.isArray(manifest.content_scripts)) {
          manifest.content_scripts.forEach((cs: any) => {
            if (Array.isArray(cs.js)) {
              cs.js = cs.js.map((scriptPath: string) => scriptPath.replace(/\.ts$/, '.js'));
            }
          });
        }
        writeFileSync(resolve(__dirname, 'dist/manifest.json'), JSON.stringify(manifest, null, 2), 'utf-8');

        // 3. Copy icons directory to dist
        if (existsSync(resolve(__dirname, 'icons'))) {
          mkdirSync(resolve(__dirname, 'dist/icons'), { recursive: true });
          const iconFiles = ['icon-16.png', 'icon-48.png', 'icon-128.png'];
          for (const iconFile of iconFiles) {
            const srcPath = resolve(__dirname, 'icons', iconFile);
            if (existsSync(srcPath)) {
              writeFileSync(resolve(__dirname, 'dist/icons', iconFile), readFileSync(srcPath));
            }
          }
        }

        // 4. Ensure config.json in dist
        const configSrc = resolve(__dirname, 'src/config.json');
        if (existsSync(configSrc)) {
          writeFileSync(resolve(__dirname, 'dist/config.json'), readFileSync(configSrc));
        } else if (!existsSync(resolve(__dirname, 'dist/config.json'))) {
          writeFileSync(resolve(__dirname, 'dist/config.json'), JSON.stringify({
            api_url: 'https://wasender.sirkelta.my.id/api/extension',
            app_url: 'https://wasender.sirkelta.my.id'
          }, null, 2), 'utf-8');
        }

        // 5. Code Obfuscation (Anti-Theft & Reverse-Engineering Protection)
        const obfFunc = (JavaScriptObfuscator as any).obfuscate || (JavaScriptObfuscator as any).default?.obfuscate;
        const allJsFiles = getAllJsFiles(resolve(__dirname, 'dist'));
        console.log(`[Obfuscator] Obfuscating ${allJsFiles.length} JavaScript files...`);
        for (const jsFile of allJsFiles) {
          const originalCode = readFileSync(jsFile, 'utf-8');
          const obfuscated = obfFunc(originalCode, {
            compact: true,
            controlFlowFlattening: true,
            controlFlowFlatteningThreshold: 0.75,
            numbersToExpressions: true,
            simplify: true,
            splitStrings: true,
            splitStringsChunkLength: 6,
            stringArray: true,
            stringArrayEncoding: ['base64'],
            stringArrayRotate: true,
            stringArrayShuffle: true,
            stringArrayThreshold: 0.8,
            transformObjectKeys: true,
            identifierNamesGenerator: 'hexadecimal',
            target: 'browser',
          });
          writeFileSync(jsFile, obfuscated.getObfuscatedCode(), 'utf-8');
          console.log(`[Obfuscator] ✓ Obfuscated: ${jsFile.replace(__dirname, '')}`);
        }
      }
    }
  ]
});
