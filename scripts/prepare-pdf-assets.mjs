import { cp, mkdir } from 'node:fs/promises';

// Static PDF fonts/decoders are served locally, never from a third-party CDN.
const source = new URL('../node_modules/pdfjs-dist/', import.meta.url);
const target = new URL('../public/pdfjs/', import.meta.url);
await mkdir(target, { recursive: true });
for (const directory of ['cmaps', 'standard_fonts', 'wasm', 'iccs']) {
    await cp(new URL(directory, source), new URL(directory, target), { recursive: true });
}
await cp(new URL('LICENSE', source), new URL('LICENSE', target));
