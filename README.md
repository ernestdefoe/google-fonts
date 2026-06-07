# Google Fonts for Flarum

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/ernestdefoe/google-fonts/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/google-fonts.svg)](https://packagist.org/packages/ernestdefoe/google-fonts)

Use **any Google Font** on your Flarum 2 forum — *or upload and self-host your
own*. Pick a body font and a heading font, applied site-wide, with a live
preview right in the admin panel.

## Features

- 🔎 **Searchable picker** for a curated list of popular Google Fonts, in the
  admin settings — and you can type/paste **any** Google Font family name, even
  ones not in the list.
- ⬆️ **Upload your own font** (self-hosting): upload one `.woff2` per weight and
  the forum serves the font itself, with **no call to Google**. This works
  everywhere — including regions where Google Fonts is blocked, such as
  **mainland China** — and avoids the GDPR concern of fetching fonts from
  Google on every page view.
- ✍️ **Separate body and heading fonts**, so you can pair (e.g.) *Inter* body
  text with *Playfair Display* headings.
- 👀 **Live preview** of each font directly in the admin panel.
- ⚡ **Server-side injection** into the page `<head>` — no flash of unstyled
  text, no client round-trip. The Google stylesheet is loaded
  **non-render-blocking**, so even on the Google path an unreachable Google
  never stalls page paint; visitors simply keep the system-font fallback.
- 🪶 No configuration files, no API key required.

## Installation

```bash
composer require ernestdefoe/google-fonts
```

Then enable **Google Fonts** in your admin panel.

## Usage

1. Go to **Admin → Google Fonts**.
2. Set a **Body font** and (optionally) a **Heading font**.
3. Start typing to search, or paste an exact family name from
   [fonts.google.com](https://fonts.google.com).
4. Click **Save**. The fonts apply to your forum immediately.

Leave a field blank to fall back to your theme's default font.

### Self-hosting a font (works where Google is blocked)

1. Under a font, click **Upload your own font**.
2. Give the font a **name** (this is just the family label used internally).
3. For each weight you want (Regular, Bold, …), pick the weight and upload a
   `.woff2` file. A **variable** `.woff2` covers every weight in a single file;
   a static file gets browser-synthesized bold for weights you don't upload.
4. Uploads save **immediately** — no Save click needed for the files.

Only `.woff2` is accepted (it's the smallest, universally-supported web font
format). If you have a `.ttf`/`.otf`, convert it once with any free online
woff2 converter. To go back to a Google font, click **Use a Google font
instead** (this removes the uploaded files).

## Updating

```bash
composer update ernestdefoe/google-fonts
php flarum cache:clear
```

## License

[MIT](LICENSE)
