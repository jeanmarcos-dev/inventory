# jeanmarcos/inventory — Community MSI distribution (Magento 2.4.7)

A redistributable fork of Magento **Multi-Source Inventory (MSI)** that ships
curated community fixes ahead of the upstream release cadence.

> This is a modified derivative of [`magento/inventory`](https://github.com/magento/inventory)
> (Copyright Adobe), redistributed under **AFL-3.0**. Not affiliated with or
> endorsed by Adobe. See [`NOTICE`](NOTICE) for full attribution.

## What this is

This branch (`dist-2.4.7`) targets **Magento Open Source 2.4.7** (PHP 8.1 - 8.3).
The PHP code keeps its original `Magento_Inventory*` module names and
`Magento\Inventory*` namespaces, so it is a **drop-in replacement**. Only the
Composer package identity changes.

## How it works

The single package `jeanmarcos/inventory` uses Composer's `replace` directive
to provide the 72 open-source MSI modules that ship with Magento 2.4.7.
Its `require` pins `magento/framework` to the 2.4.7 line, so Composer will
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
composer require "jeanmarcos/inventory:2.4.7.*"
# or let the framework gate auto-select the right build:
composer require "jeanmarcos/inventory:*"
bin/magento setup:upgrade
```

## Versioning

Releases are tagged **`2.4.7.<n>`** (e.g. `2.4.7.0`, `2.4.7.1`) — the
4th segment is this distribution's release counter for the Magento 2.4.7 line.
This mirrors the target Magento version and does not collide with Adobe's own
`-pN` security-patch naming. Each Magento line has its own `dist-<version>` branch.

## Differences from upstream

- Curated fixes applied on top of the Magento 2.4.7 MSI baseline (cherry-picked
  commits are tagged `[picked #NNNN]`).
- **Source-level reservations** (opt-in, default off): the global config
  `cataloginventory/source_reservations/enabled` (Stores > Configuration >
  Catalog > Inventory > Source-Level Reservations) splits each sales
  reservation into one row per source, allocated across the enabled sources of
  the stock in priority order. Reservations then affect the salable quantity of
  **every stock sharing the source**, closing the cross-stock oversell gap, and
  compensations (shipment, cancellation, credit memo) always land on the sources
  the demand was originally allocated to, regardless of which source ships.
  Notes:
  - Toggling the flag requires a full `bin/magento indexer:reindex inventory`.
  - Orders placed while the flag was off are compensated stock-scoped, exactly
    as before; mixed states are safe.
  - `inventory:reservation:create-compensations` still creates stock-scoped
    compensations; per-source residues of such orders are kept (not deleted by
    the cleanup cron) and remain visible until compensated per source.
  - Concurrent orders on different stocks sharing a source are not serialized
    against each other (the place-order lock is per stock); totals per stock
    are always preserved.
- **Storefront stock visualizer** (opt-in, default off): a product-page
  *Availability* panel driven by MSI, shipped as the additive
  `Magento_InventoryStockVisualizer` module (no core module is replaced). The
  global config `cataloginventory/stock_visualizer/enabled` (Stores >
  Configuration > Catalog > Inventory > Storefront Stock Visualizer) renders a
  traffic-light level (server-side, no quantity exposed) or the exact salable
  quantity over a cacheable AJAX fragment, aggregate or broken down per source
  (source-reservation aware). Composite products resolve their availability by
  type — the selected configurable variant, the sellable bundle count, a
  per-component breakdown, or an aggregate in-stock status — each selectable in
  the admin. Out-of-stock products render the status alone: the panel skips the
  client component, so no call to action is offered and no fragment is requested
  for an availability the server already resolved to zero. A dedicated cache tag
  keeps the panel fresh on both demand (reservation) and supply (source-item)
  changes; the purge runs synchronously or over a database-backed queue. Notes:
  - Run `bin/magento setup:upgrade` (registers the per-product attributes and the
    message-queue topology) and `setup:di:compile` for production.
  - When the purge strategy resolves to the queue, run the consumer
    `bin/magento queue:consumers:start inventory.stockvisualizer.purge` (or the
    standard consumer cron).
  - See [`InventoryStockVisualizer/README.md`](InventoryStockVisualizer/README.md)
    for the full configuration and cache-invalidation architecture.
- The proprietary `InventoryRequisitionList` module is **removed** (not covered by
  the OSL-3.0 / AFL-3.0 open source license).

## License

Redistributed under **AFL-3.0**. Original Adobe copyright and license notices
are retained in every source file, as required. See [`LICENSE_AFL.txt`](LICENSE_AFL.txt)
and [`NOTICE`](NOTICE).
