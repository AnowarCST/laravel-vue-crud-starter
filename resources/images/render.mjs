// Renders resources/images/products/*.svg (800x600) to WebP with headless
// Chromium. See resources/images/README.md for how to run it.
//
// Usage: node render.mjs <svg-directory> <output-directory>
import { chromium } from 'playwright';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import { basename, join } from 'node:path';

const [source, target] = process.argv.slice(2);
if (!source || !target) {
    console.error('Usage: node render.mjs <svg-directory> <output-directory>');
    process.exit(1);
}

const QUALITY = 0.9;

await mkdir(target, { recursive: true });
const browser = await chromium.launch();
const page = await browser.newPage();
await page.setContent('<canvas id="canvas" width="800" height="600"></canvas>');

const files = (await readdir(source)).filter((file) => file.endsWith('.svg')).sort();
for (const file of files) {
    const svg = await readFile(join(source, file));
    const webp = await page.evaluate(
        async ({ base64, quality }) => {
            const image = new Image();
            image.src = `data:image/svg+xml;base64,${base64}`;
            await image.decode();
            const canvas = document.getElementById('canvas');
            const context = canvas.getContext('2d');
            context.clearRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/webp', quality).split(',')[1];
        },
        { base64: svg.toString('base64'), quality: QUALITY },
    );
    const output = join(target, `${basename(file, '.svg')}.webp`);
    const bytes = Buffer.from(webp, 'base64');
    await writeFile(output, bytes);
    console.log(`${output} (${bytes.length} bytes)`);
}

await browser.close();
