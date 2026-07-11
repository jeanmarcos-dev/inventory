# jeanmarcos/inventory — Community MSI distribution (Magento 2.4.8)

A redistributable fork of Magento **Multi-Source Inventory (MSI)** that ships
curated community fixes ahead of the upstream release cadence.

> This is a modified derivative of [`magento/inventory`](https://github.com/magento/inventory)
> (Copyright Adobe), redistributed under **AFL-3.0**. Not affiliated with or
> endorsed by Adobe. See [`NOTICE`](NOTICE) for full attribution.

## What this is

This branch (`dist-2.4.8`) targets **Magento Open Source 2.4.8** (PHP 8.2 - 8.4).
The PHP code keeps its original `Magento_Inventory*` module names and
`Magento\Inventory*` namespaces, so it is a **drop-in replacement**. Only the
Composer package identity changes.

## How it works

The single package `jeanmarcos/inventory` uses Composer's `replace` directive
to provide the 73 open-source MSI modules that ship with Magento 2.4.8.
Its `require` pins `magento/framework` to the 2.4.8 line, so Composer will
only install this build on a matching Magento version and auto-selects the right
build across versions.

## Installation

```jsonc
// composer.json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/jeanmarcos-dev/inventory" }
    ]
}
```

```bash
# explicit for this line:
composer require "jeanmarcos/inventory:2.4.8.*"
# or let the framework gate auto-select the right build:
composer require "jeanmarcos/inventory:*"
bin/magento setup:upgrade
```

## Versioning

Releases are tagged **`2.4.8.<n>`** (e.g. `2.4.8.0`, `2.4.8.1`) — the
4th segment is this distribution's release counter for the Magento 2.4.8 line.
This mirrors the target Magento version and does not collide with Adobe's own
`-pN` security-patch naming. Each Magento line has its own `dist-<version>` branch.

## Differences from upstream

- Curated fixes applied on top of the Magento 2.4.8 MSI baseline (cherry-picked
  commits are tagged `[picked #NNNN]`).
- The proprietary `InventoryRequisitionList` module is **removed** (not covered by
  the OSL-3.0 / AFL-3.0 open source license).

## License

Redistributed under **AFL-3.0**. Original Adobe copyright and license notices
are retained in every source file, as required. See [`LICENSE_AFL.txt`](LICENSE_AFL.txt)
and [`NOTICE`](NOTICE).
