# Playdate Dash Docset

A single [Dash](https://kapeli.com/dash) docset bundling the official Playdate
developer documentation:

- Inside Playdate (Lua API)
- Inside Playdate with C (C API)
- Designing for Playdate

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
wget -k -np -p -H --adjust-extension https://sdk.play.date/inside-playdate
wget -k -np -p -H --adjust-extension https://sdk.play.date/inside-playdate-with-c
wget -k -np -p -H --adjust-extension https://help.play.date/developer/designing-for-playdate/
wget -k -np -p -H --adjust-extension https://play.date/dev/
cd ..
```

Build the docset:

```
composer build
```

The result is written to `storage/playdate/playdate.docset` (and archived as
`storage/playdate/playdate.tgz`). Import it into Dash or Zeal.

`composer build` loads `pdo_sqlite` with `-d`; if the extension is enabled in
your `php.ini`, `vendor/bin/dash-docset build Playdate` also works directly.
