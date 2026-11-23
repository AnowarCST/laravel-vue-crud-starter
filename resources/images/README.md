# Image sources

## Logo

`brand/logo.svg` is the application logo: a product box with a check mark on a
rounded tile. It was drawn for this project and is released under the
repository's [MIT license](../../LICENSE). It is copied unchanged to
`public/images/logo.svg` (sidebar and sign-in pages) and
`public/favicon.svg`. `public/favicon.ico` (16, 32 and 48 px) and
`public/apple-touch-icon.png` (180 px) are renders of the same file.

## Product illustrations

`products/*.svg` are the sources of the demo product images used by the
database seeder. They were drawn for this project: no third-party images,
icon fonts, logos or brand marks were used. They are released under the
repository's [MIT license](../../LICENSE).

Each file is a flat illustration on an 800 × 600 canvas with the same colour
palette, a soft background and the object in the centre. The rendered images
are in `public/images/products/<name>.webp` (800 × 600, WebP), and seeded
products reference them as `images/products/<name>.webp`.

### Re-rendering

After changing or adding an SVG, render the WebP files again with headless
Chromium from the Playwright Docker image. Run this from the repository root;
Playwright is installed in a temporary directory, not in the project:

```bash
mkdir -p /tmp/render-products && cp resources/images/render.mjs /tmp/render-products/
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -v /tmp/render-products:/render \
  -v "$PWD/resources/images/products:/svg:ro" \
  -v "$PWD/public/images/products:/out" \
  -w /render mcr.microsoft.com/playwright:v1.65.0-noble \
  sh -c "npm init -y >/dev/null && npm install --no-audit --no-fund playwright@1.65.0 && node render.mjs /svg /out"
```

`render.mjs` writes one `<name>.webp` per `<name>.svg` (quality 0.9) and
prints each file's size. Keep every image under about 60 KB; the seeder test
checks this limit.
