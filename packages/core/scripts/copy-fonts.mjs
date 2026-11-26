// Copies the Inter variable font subsets we ship (latin, latin-ext, cyrillic, cyrillic-ext) next to the built CSS.
import { copyFileSync, mkdirSync } from 'node:fs';

const target = new URL('../resources/dist/fonts/', import.meta.url);
mkdirSync(target, { recursive: true });
for (const subset of ['latin', 'latin-ext', 'cyrillic', 'cyrillic-ext']) {
    const file = `inter-${subset}-wght-normal.woff2`;
    copyFileSync(new URL(`../node_modules/@fontsource-variable/inter/files/${file}`, import.meta.url), new URL(file, target));
}
