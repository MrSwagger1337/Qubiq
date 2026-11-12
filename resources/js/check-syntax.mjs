import { execFileSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const files = readdirSync(here).filter((f) => f.endsWith('.js') || f.endsWith('.mjs'));

for (const file of files) {
    execFileSync(process.execPath, ['--check', join(here, file)], { stdio: 'inherit' });
}

console.log(`Syntax check passed for ${files.length} JS modules.`);

