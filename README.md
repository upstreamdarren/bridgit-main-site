# Bridgit Care website

The code-based public website for `bridgit.care`. It is built with Astro and
designed to deploy as a static Cloudflare Pages project.

## Local development

```sh
npm install
npm run dev
```

## Production build

```sh
npm run build
```

Cloudflare Pages settings:

- Build command: `npm run build`
- Build output directory: `dist`
- Production branch: `main`

Every non-production branch can be deployed to a Cloudflare preview URL before
it is merged.

## Content ownership

- The public marketing site lives in this repository.
- WordPress remains the blog editor during phase one.
- `/blog` stays reserved for WordPress. Its final reverse-proxy origin will be
  configured before the root domain is moved to Cloudflare Pages.
- Product applications on Bridgit subdomains are not part of this repository.

## Editing the homepage

- Page copy and sections: `src/pages/index.astro`
- Header and navigation: `src/components/Header.astro`
- Footer and organisation details: `src/components/Footer.astro`
- Shared colours, typography and layouts: `src/styles/global.css`
