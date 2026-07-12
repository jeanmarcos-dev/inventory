# jeanmarcos/inventory

Community distribution of Magento **Multi-Source Inventory (MSI)** with curated
community fixes shipped ahead of Adobe's upstream release cadence, redistributed
under **AFL-3.0**.

It is a **drop-in replacement** for the open-source MSI modules: it keeps the
original `Magento_Inventory*` module names and `Magento\Inventory*` namespaces,
so no application code changes are required.

> Derived from [magento/inventory](https://github.com/magento/inventory)
> (Copyright Adobe). Not affiliated with or endorsed by Adobe.

## Exclusive features

- **Source-level reservations** (2.4.9.6+, opt-in): the global setting
  *Stores > Configuration > Catalog > Inventory > Source-Level Reservations*
  (`cataloginventory/source_reservations/enabled`, default off) splits each
  sales reservation into one row per source, allocated across the enabled
  sources of the stock in priority order. Reservations then affect the salable
  quantity of **every stock sharing the source**, closing the cross-stock
  oversell gap, and compensations (shipment, cancellation, credit memo) always
  land on the sources the demand was originally allocated to — even when the
  shipment is dispatched from a different source. Toggling the setting requires
  a full inventory reindex.

## Installation

```bash
composer require "jeanmarcos/inventory:2.4.9.*"
```

Pick the constraint that matches your Magento line:

| Magento line | Constraint  | `magento/framework`      |
|--------------|-------------|--------------------------|
| 2.4.7        | `2.4.7.*`   | `>=103.0.7 <103.0.8`     |
| 2.4.8        | `2.4.8.*`   | `>=103.0.8 <103.0.9`     |
| 2.4.9        | `2.4.9.*`   | `>=103.0.9 <103.0.10`    |

Each release pins `magento/framework` to a single Magento line, so Composer
automatically selects the build matching your installation.

If you install from the Git repository directly instead of Packagist, add it as
a VCS repository first:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/jeanmarcos-dev/inventory" }
    ]
}
```

## Versioning

Releases are tagged `2.4.<line>.<n>`, where `<line>` mirrors the Magento minor
line and `<n>` is this distribution's release counter (independent of Adobe's
`-pN` patch naming).

## Branches

- `dist-2.4.7`, `dist-2.4.8`, `dist-2.4.9` — the per-line distributions
  (packaging plus curated fixes). Releases are tagged here.
- `develop` — untouched mirror of upstream `magento/inventory`.

## License

Redistributed under [AFL-3.0](https://opensource.org/licenses/AFL-3.0). The
original Adobe/Magento copyright headers are retained in every source file.
