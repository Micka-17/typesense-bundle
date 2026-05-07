# Zero-downtime reindex with Collection Aliases

A **collection alias** is a stable pointer to an underlying collection.  
Your app always queries `products` (the alias); behind the scenes you swap it from `products_v1` to `products_v2` without downtime.

---

## How it works

```
alias "products" → products_v1   ← live traffic
                                   ↓ (reindex in background)
                   products_v2   ← ready
                                   ↓ (atomic swap)
alias "products" → products_v2   ← live traffic
                                   ↓ (cleanup)
                   products_v1   ← deleted
```

---

## Step-by-step via CLI

### 1. Create the first collection and alias

```bash
# Sync creates collections from your entity config
php bin/console micka17:typesense:sync

# Then create the alias pointing to the real collection name
php bin/console micka17:typesense:aliases:upsert products products_v1
```

### 2. Verify

```bash
php bin/console micka17:typesense:aliases:list
# Name       Collection
# products   products_v1
```

### 3. Reindex into a new collection

When you change the schema (new field, new analyzer…), create a new collection and index into it while the alias still points to the old one:

```bash
# Recreate the collection with the new schema under a versioned name
php bin/console micka17:typesense:manage create products_v2

# Index all documents into products_v2
php bin/console typesense:reindex "App\Entity\Product" --collection=products_v2
```

> If your `ReindexCommand` doesn't support `--collection`, index manually by temporarily
> changing the collection name in your entity's `#[TypesenseIndexable(collection: 'products_v2')]`
> before running the reindex, then restoring it after the swap.

### 4. Swap the alias (zero downtime)

```bash
php bin/console micka17:typesense:aliases:upsert products products_v2
```

From this moment, all search traffic hits `products_v2`.

### 5. Clean up the old collection

```bash
php bin/console micka17:typesense:manage delete products_v1
```

---

## Step-by-step via PHP (AliasManager)

```php
use Micka17\TypesenseBundle\Service\AliasManager;
use Micka17\TypesenseBundle\Service\TypesenseManager;

class ReindexService
{
    public function __construct(
        private readonly AliasManager $aliases,
        private readonly TypesenseManager $manager,
    ) {}

    public function reindex(string $entityClass, string $aliasName, string $newCollectionName): void
    {
        // 1. Create the new collection
        $this->manager->createCollectionForEntity($entityClass, $newCollectionName);

        // 2. Index all documents (your app continues serving from the old collection)
        $this->manager->reindexEntityCollection($entityClass, $newCollectionName);

        // 3. Atomic swap — returns the previous collection name
        $previous = $this->aliases->swapAlias($aliasName, $newCollectionName);

        // 4. Delete the old collection
        if ($previous !== null) {
            $this->manager->deleteCollection($previous);
        }
    }
}
```

### `swapAlias()` return value

```php
$previous = $this->aliases->swapAlias('products', 'products_v2');
// $previous = 'products_v1'  → alias existed, returns the old target
// $previous = null           → alias was new, nothing to clean up
```

---

## Naming conventions

| Style | Example |
|---|---|
| Timestamp suffix | `products_20260101` |
| Version suffix | `products_v1`, `products_v2` |
| Blue/green | `products_blue`, `products_green` |

Timestamp suffixes are best for automated pipelines (no manual counter to track).

---

## Configure your app to use the alias

In `typesense.yaml`, always use the alias name as the collection:

```yaml
typesense:
    indexable_entities:
        App\Entity\Product:
            collection: products   # ← this is the alias, not a real collection
```

In `FinderService`:

```php
$result = $finder->search('products', ['q' => 'laptop', 'query_by' => 'name']);
//                         ^^^^^^^^^ alias — resolves transparently
```

---

## Dashboard

Go to `/admin/typesense/aliases` to view and delete aliases via the web UI.
