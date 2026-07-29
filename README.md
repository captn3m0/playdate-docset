# Playdate Dash Docset

[![Playdate SDK](https://img.shields.io/github/v/release/captn3m0/playdate-docset?label=Playdate%20SDK&color=orange)](https://github.com/captn3m0/playdate-docset/releases/latest)
[![Build](https://img.shields.io/github/actions/workflow/status/captn3m0/playdate-docset/build.yml?label=daily%20build)](https://github.com/captn3m0/playdate-docset/actions/workflows/build.yml)

A single [Dash](https://kapeli.com/dash) docset bundling the official Playdate
developer documentation:

- Inside Playdate (Lua API)
- Inside Playdate with C (C API)
- Designing for Playdate

A [GitHub Actions workflow](.github/workflows/build.yml) rebuilds the docset
every day, tracks the upstream SDK version (recorded in
[`version.txt`](version.txt) and shown by the badge above), and publishes a
release tagged with that version.

I found out after building this that the SDK comes with a Docset, but it isn't as good.

## Download

Grab the latest build from the
[releases page](https://github.com/captn3m0/playdate-docset/releases/latest), or
use the permanent link (always the newest release):

```
https://github.com/captn3m0/playdate-docset/releases/latest/download/Playdate.tgz
```

Unpack it and import the resulting `Playdate.docset` into Dash or Zeal.

It is generated with
[godbout/dash-docset-builder](https://github.com/godbout/dash-docset-builder),
installed as a Composer dependency. The only custom code is
`app/Docsets/Playdate.php`. It produces 1,720 typed entries (functions, methods,
callbacks, properties, variables, classes, modules, sections), strips all
JavaScript and site chrome for offline use, adds a landing page linking the
three docs, fixes lazy-loaded images, and rewrites cross-document links to point
locally.

## Requirements

PHP 8 with the `pdo_sqlite` extension, and Composer.

## Build

Install the builder:

```
composer install --ignore-platform-reqs
```

Download the source docs into `html/` (once):

```
mkdir -p html && cd html
wget --trust-server-names -k -np -p -H --adjust-extension https://sdk.play.date/inside-playdate
wget --trust-server-names -k -np -p -H --adjust-extension https://sdk.play.date/inside-playdate-with-c
wget -k -np -p -H --adjust-extension https://help.play.date/developer/designing-for-playdate/
wget -k -np -p -H --adjust-extension https://play.date/dev/
cd ..
```

`--trust-server-names` makes wget follow the `/inside-playdate` redirect and
save the reference at its versioned path (`sdk.play.date/<version>/…`), which is
the layout the builder derives the SDK version from.

Build the docset:

```
composer build
```

The result is written to `storage/Playdate/Playdate.docset` (and archived as
`storage/Playdate/Playdate.tgz`, which unpacks to `Playdate.docset`). Import it
into Dash or Zeal.

`composer build` loads `pdo_sqlite` with `-d`; if the extension is enabled in
your `php.ini`, `vendor/bin/dash-docset build Playdate` also works directly.
