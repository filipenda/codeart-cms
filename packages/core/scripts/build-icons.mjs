// Reads scripts/icons.txt and writes resources/icons/lucide.json: { name: "<inner svg markup>" }.
// Only the listed icons are shipped. Source: the `lucide-static` package (ISC licence).
import { readFileSync, writeFileSync } from 'node:fs';

const names = readFileSync(new URL('./icons.txt', import.meta.url), 'utf8')
    .split('\n').map((l) => l.trim()).filter((l) => l !== '' && !l.startsWith('#'));

const out = {};
for (const name of [...new Set(names)].sort()) {
    const file = new URL(`../node_modules/lucide-static/icons/${name}.svg`, import.meta.url);
    let svg;
    try { svg = readFileSync(file, 'utf8'); } catch { throw new Error(`Unknown Lucide icon "${name}"`); }
    out[name] = svg.replace(/<!--[\s\S]*?-->/g, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>[\s\S]*$/, '').replace(/\s*\n\s*/g, '').trim();
}
writeFileSync(new URL('../resources/icons/lucide.json', import.meta.url), JSON.stringify(out, null, 1) + '\n');
console.log(`${Object.keys(out).length} icons written`);
