import { cpSync, mkdirSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const outDir = join(here, '..', '..', 'public', 'assets', 'js');

mkdirSync(outDir, { recursive: true });

for (const entry of readdirSync(here)) {
    if (entry.endsWith('.js')) {
        cpSync(join(here, entry), join(outDir, entry));
    }
}

console.log('Copied modular ES scripts into public/assets/js/');

