# Google Fonts for Flarum

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/ernestdefoe/google-fonts/blob/main/LICENSE)
[![Latest Stable Version](https://img.shields.io/packagist/v/ernestdefoe/google-fonts.svg)](https://packagist.org/packages/ernestdefoe/google-fonts)

Use **any Google Font** on your Flarum 2 forum. Pick a body font and a heading
font from the full Google Fonts library — applied site-wide, with a live preview
right in the admin panel.

## Features

- 🔎 **Searchable picker** for a curated list of popular Google Fonts, in the
  admin settings — and you can type/paste **any** Google Font family name, even
  ones not in the list.
- ✍️ **Separate body and heading fonts**, so you can pair (e.g.) *Inter* body
  text with *Playfair Display* headings.
- 👀 **Live preview** of each font directly in the admin panel.
- ⚡ **Server-side injection** of the Google Fonts stylesheet into the page
  `<head>` — no flash of unstyled text, no client round-trip.
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

## Updating

```bash
composer update ernestdefoe/google-fonts
php flarum cache:clear
```

## License

[MIT](LICENSE)
