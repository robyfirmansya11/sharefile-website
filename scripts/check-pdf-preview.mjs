import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { getDocument } from 'pdfjs-dist/legacy/build/pdf.mjs';

// In-memory fixture only: no user documents or output files are read/written.
const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 240 160] /Resources << /Font << /F1 5 0 R >> >> /Contents 6 0 R >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 240 160] /Resources << /Font << /F1 5 0 R >> >> /Contents 7 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
];
for (const number of [1, 2]) {
    const stream = `0.1 0.6 0.5 rg 20 20 100 30 re f BT /F1 16 Tf 20 90 Td (Sharefolder page ${number}) Tj ET\n`;
    objects.push(`<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}endstream`);
}
let content = '%PDF-1.4\n';
const offsets = [0];
objects.forEach((object, index) => { offsets.push(Buffer.byteLength(content)); content += `${index + 1} 0 obj\n${object}\nendobj\n`; });
const xref = Buffer.byteLength(content);
content += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
content += offsets.slice(1).map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('');
content += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
const task = getDocument({ data: new Uint8Array(Buffer.from(content)), standardFontDataUrl: fileURLToPath(new URL('../node_modules/pdfjs-dist/standard_fonts/', import.meta.url)).replaceAll('\\', '/') });
try {
    const pdf = await task.promise;
    assert.equal(pdf.numPages, 2);
    for (const number of [1, 2]) {
        const page = await pdf.getPage(number);
        const text = await page.getTextContent();
        assert(text.items.some(item => item.str === `Sharefolder page ${number}`));
        const viewport = page.getViewport({ scale: number });
        const target = pdf.canvasFactory.create(viewport.width, viewport.height);
        await page.render({ canvasContext: target.context, viewport }).promise;
        const pixels = target.context.getImageData(25 * number, 120 * number, 1, 1).data;
        assert(pixels[1] > pixels[0], 'Teal rectangle was rendered');
        pdf.canvasFactory.destroy(target);
        page.cleanup();
    }
    console.log('PDF smoke check passed: two pages parsed and rendered at 100% / 200%.');
} finally { await task.destroy(); }
