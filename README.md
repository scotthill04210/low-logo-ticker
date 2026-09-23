# LOW Logo Ticker

WordPress plugin that renders a continuously scrolling logo ticker.

**Version:** 1.0.8  
**Author:** Scott Hill  
**Requires:** WordPress 6.0+, PHP 7.4+  
**Updates:** [GitHub Releases](https://github.com/scotthill04210/low-logo-ticker/releases)

## Install

1. Copy the `LOW-logo-ticker` folder into `wp-content/plugins/`.
2. Activate **LOW Logo Ticker** under Plugins.
3. Open **Logo Ticker** in the admin menu (above Posts).

## Add logos

On the **Logos** tab:

1. Click **Add Logo**, then **Choose Image** and pick a file from the media library (JPEG, PNG, GIF, WebP, AVIF, or SVG).
2. Enter a **Name**. That value is used for alt text and, unless you hide it in Settings, the hover tooltip.
3. Drag the handle on the left to change scroll order. **Duplicate** copies a row; **×** removes it.
4. Click **Save Changes**. Rows without an image are discarded.

Up to 80 logos can be saved.

## Display settings

On the **Settings** tab:

- **Image height** — desktop logo height in pixels (default 40). Tablet and mobile sizes scale down from that value.
- **Hide title on hover** — removes the browser tooltip. Alt text from the Name field is kept.

Saving Settings does not change the logo list. Saving Logos does not change display settings.

## Place the ticker

Paste this shortcode into a page, post, text widget, or a Shortcode / HTML block:

```
[low_logo_ticker]
```

If no logos are saved, the shortcode outputs nothing.

The ticker loops without a gap, even with only a few logos. Logos are not links and do not pause on hover. Visitors who prefer reduced motion see a static, horizontally scrollable row instead of the animation.

In-plugin instructions are also on the **Documentation** tab.

## Updates

WordPress checks [GitHub Releases](https://github.com/scotthill04210/low-logo-ticker/releases). On the Plugins screen, use **Check for update**.

Release zips must have this layout so the plugin path does not change:

```
LOW-logo-ticker/
  low-logo-ticker.php
  includes/
  assets/
```

Do not attach a GitHub source archive (`low-logo-ticker-1.0.8/`). Publishing a release runs a workflow that builds the correct zip.

## File structure

```
low-logo-ticker.php
includes/
  class-admin-page.php
  class-shortcode.php
  class-cache.php
  class-github-updater.php
assets/
  css/admin.css
  css/ticker.css
  js/admin.js
  js/ticker.js
```
