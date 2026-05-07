# micka-17/typesense-bundle

Symfony bundle for [Typesense](https://typesense.org) — full-text search, vector search, and AI-powered search for your Doctrine entities.

[![PHP](https://img.shields.io/badge/PHP-8.5%2B-blue)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0-black)](https://symfony.com)
[![Typesense](https://img.shields.io/badge/Typesense-30%2B-orange)](https://typesense.org)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Compatibility

| Bundle | PHP  | Symfony       | Typesense Server | typesense-php |
|--------|------|---------------|------------------|---------------|
| 2.x    | ≥8.5 | ^7.4 \| ^8.0 | ≥30              | ^6.0          |
| 1.x    | ≥8.1 | ^6.4 \| ^7.0 | 26–29            | ^5.0          |

---

## Installation

```bash
composer require micka-17/typesense-bundle
```

Register the bundle (if not using Symfony Flex):

```php
// config/bundles.php
return [
    Micka17\TypesenseBundle\TypesenseBundle::class => ['all' => true],
];
```

---

## Quick Start

### 1. Configure the bundle

```yaml
# config/packages/typesense.yaml
typesense:
    api_key: '%env(TYPESENSE_API_KEY)%'
    cluster:
        nodes:
            - { host: '%env(TYPESENSE_HOST)%', port: 8108, protocol: http }

    indexable_entities:
        App\Entity\Product: ~
        App\Entity\Category: ~

    auto_update: true   # auto-index on Doctrine persist/update/remove
```

```dotenv
# .env
TYPESENSE_API_KEY=xyz
TYPESENSE_HOST=localhost
```

### 2. Annotate your entities

```php
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Attribute\TypesenseField;

#[TypesenseIndexable(collection: 'products')]
class Product
{
    #[TypesenseField(type: 'string', sort: true)]
    private string $name;

    #[TypesenseField(type: 'float', facet: true)]
    private float $price;

    #[TypesenseField(type: 'string[]', facet: true)]
    private array $tags = [];

    #[TypesenseField(type: 'int32')]
    private int $stock;
}
```

### 3. Create collections and index data

```bash
php bin/console micka17:typesense:sync
```

### 4. Search

```php
use Micka17\TypesenseBundle\Service\FinderService;

class ProductController
{
    public function __construct(private readonly FinderService $finder) {}

    public function search(string $q): array
    {
        $result = $this->finder->search('products', [
            'q'        => $q,
            'query_by' => 'name',
            'filter_by' => 'stock:>0',
        ]);

        return $result->hits; // array of documents
    }
}
```

---

## Configuration Reference

```yaml
typesense:
    api_key: '%env(TYPESENSE_API_KEY)%'

    # --- Cluster ---
    cluster:
        enabled: false                  # true for multi-node HA setup
        nodes:
            - { host: localhost, port: 8108, protocol: http }
        read_preference: nearest        # nearest | leader | follower
        consistency_level: eventual     # eventual | strong

    # --- Entities to index ---
    indexable_entities:
        App\Entity\Product: ~
        App\Entity\Category: ~

    # --- Auto-index on Doctrine events ---
    auto_update: true

    # --- Error tracking ---
    error_tracking:
        enabled: true
        log_level: error
        track_node_errors: false
        node_error_fields: [host, port, error_message]

    # ── V2 Resources (Typesense 30+) ──────────────────────────────────────

    synonym_sets:
        main:
            items:
                electronics:
                    synonyms: [phone, mobile, smartphone]
                size:
                    root: large
                    synonyms: [big, huge, xl, xxl]

    curation_sets:
        featured:
            items:
                promote-iphone:
                    rule: { query: iphone }
                    includes:
                        - { id: 'product-42', position: 1 }

    presets:
        product_default:
            value:
                query_by: name,description
                per_page: 20
                sort_by: _text_match:desc

    stemming_dictionaries:
        french:
            words:
                - { word: chaussures, root: chaussure }
                - { word: couraient, root: courir }

    analytics_rules:
        popular_products:
            type: popular_queries
            params:
                source:
                    collections: [products]
                destination:
                    collection: popular_queries

    nl_search_models:
        products-nl:
            model_name: openai/gpt-4o-mini
            api_key: '%env(OPENAI_API_KEY)%'
            system_prompt: 'You translate natural language into Typesense search parameters.'

    conversation_models:
        support-bot:
            model_name: openai/gpt-4o-mini
            api_key: '%env(OPENAI_API_KEY)%'
            system_prompt: 'You are a helpful product assistant.'

    # --- Legacy (V1 only, migrate to synonym_sets) ---
    # synonyms: []
```

---

## Attributes

### `#[TypesenseIndexable]`

Applied on the entity class. Defines the Typesense collection.

| Parameter          | Type     | Default | Description                                        |
|--------------------|----------|---------|----------------------------------------------------|
| `collection`       | string   | —       | Collection name in Typesense                       |
| `defaultSortingField` | string | —      | Field used for default sort (must be numeric)     |
| `normalizerMethod` | string   | —       | Custom method on the entity to build the document  |
| `metadata`         | array    | []      | Arbitrary metadata attached to the collection      |
| `options`          | array    | []      | Extra collection-level options (voice_query_model…)|

### `#[TypesenseField]`

Applied on entity properties. Maps a property to a Typesense field.

| Parameter          | Type     | Default | Description                                        |
|--------------------|----------|---------|----------------------------------------------------|
| `type`             | string   | auto    | Typesense field type (`string`, `int32`, `float`, `bool`, `string[]`, `float[]`, `auto`, …) |
| `name`             | string   | —       | Override the field name in the index               |
| `facet`            | bool     | false   | Enable faceting                                    |
| `sort`             | bool     | false   | Enable sorting                                     |
| `optional`         | bool     | false   | Allow null values                                  |
| `index`            | bool     | true    | Include field in the index                         |
| `getter`           | string   | —       | Method name to call instead of reading the property|
| `reference`        | string   | —       | JOIN reference, format `collection.field`          |
| `asyncReference`   | bool     | false   | Async JOIN reference                               |
| `cascadeDelete`    | bool     | false   | Delete related document on parent delete           |
| `embed`            | array    | —       | Auto-embedding config (requires `type: auto`)      |
| `numDim`           | int      | —       | Vector dimension (required for `float[]` fields)   |
| `vecDist`          | string   | —       | Vector distance metric: `cosine`, `ip`, `l2sq`     |
| `hnswParams`       | array    | —       | HNSW index parameters                              |
| `truncate`         | int      | —       | Max characters (string fields only)                |
| `tokenSeparators`  | string[] | []      | Custom token separators                            |
| `symbolsToIndex`   | string[] | []      | Symbols to include in the index                    |

**Examples:**

```php
// Vector field with auto-embedding
#[TypesenseField(
    type: 'float[]',
    embed: ['from' => ['name', 'description'], 'model_config' => ['model_name' => 'ts/e5-small']],
    numDim: 384,
    vecDist: 'cosine',
)]
private array $embedding;

// JOIN reference
#[TypesenseField(type: 'string', reference: 'brands.id')]
private string $brandId;

// Custom getter
#[TypesenseField(type: 'string[]', getter: 'getTagNames')]
private Collection $tags;
```

---

## FinderService

Inject `Micka17\TypesenseBundle\Service\FinderService` and use these methods:

### `search(string $collection, array $params): Result`

```php
$result = $finder->search('products', ['q' => 'laptop', 'query_by' => 'name']);
$result->found      // int: total matches
$result->hits       // array of documents
$result->tookMs     // int: query time
$result->facetCounts // array of facet buckets
```

### `searchAndPaginate(string $collection, array $params, int $page, int $perPage): Paginator`

```php
$paginator = $finder->searchAndPaginate('products', ['q' => 'laptop'], page: 2, perPage: 15);
$paginator->items        // array of documents
$paginator->total        // int
$paginator->currentPage  // int
$paginator->lastPage     // int (virtual, via property hook)
$paginator->hasNextPage  // bool
$paginator->nextPage     // ?int
```

### `multiSearch(array $searchRequests): Result[]`

```php
$results = $finder->multiSearch([
    'searches' => [
        ['collection' => 'products', 'q' => 'laptop', 'query_by' => 'name'],
        ['collection' => 'categories', 'q' => 'laptop', 'query_by' => 'name'],
    ],
]);
```

### `searchWithPreset(string $presetName, array $extra = []): Result`

```php
$result = $finder->searchWithPreset('product_default');
```

### `unionSearch(array $searches, array $commonParams = []): Result` *(v30+)*

Searches multiple collections and merges results into a single ranked list. Each hit includes `_collection`.

```php
$result = $finder->unionSearch([
    ['collection' => 'books',   'q' => 'harry potter', 'query_by' => 'title'],
    ['collection' => 'movies',  'q' => 'harry potter', 'query_by' => 'title'],
], ['per_page' => 10]);
```

### `searchWithDiversification(string $collection, array $params, float $mmrLambda = 0.5, ?string $mmrEmbeddingField = null): Result` *(v30+)*

MMR (Maximal Marginal Relevance) diversification — reduces duplicate results. `mmrLambda = 1.0` → max relevance, `0.0` → max diversity.

```php
$result = $finder->searchWithDiversification(
    'products',
    ['q' => 'laptop', 'vector_query' => 'embedding:([0.1, ...])'],
    mmrLambda: 0.7,
    mmrEmbeddingField: 'embedding',
);
```

### `conversationalSearch(string $collection, array $params, string $modelId, ?string $conversationId = null): Result` *(v30+)*

RAG-style search. The model answers the query and the answer is in `$result->conversationAnswer`.

```php
$result = $finder->conversationalSearch('products', ['q' => 'best laptop for gaming'], 'support-bot');
echo $result->conversationAnswer;   // "Based on our catalog, the ASUS ROG..."
echo $result->conversationId;       // "conv-abc123" — pass on follow-ups

// Follow-up:
$result2 = $finder->conversationalSearch('products', ['q' => 'what about battery life?'], 'support-bot', $result->conversationId);
```

### `naturalLanguageSearch(string $collection, string $query, string $modelId, array $extra = []): Result` *(v30+)*

Translates a free-text question into structured search parameters server-side.

```php
$result = $finder->naturalLanguageSearch(
    'products',
    'laptops under 1000 euros with good battery',
    'products-nl',
    ['per_page' => 5],
);
```

---

## Commands

### Sync & maintenance

```bash
# Create/update collections + apply all V2 resources in one shot
php bin/console micka17:typesense:sync

# Diagnose configuration vs Typesense state
php bin/console micka17:typesense:doctor

# Re-index a specific entity
php bin/console typesense:reindex "App\Entity\Product"

# Export documents as JSONL
php bin/console micka17:typesense:documents:export products
php bin/console micka17:typesense:documents:export products --output=/tmp/export.jsonl --filter-by="stock:>0"

# Update Typesense server config dynamically
php bin/console micka17:typesense:config:update cache-num-entries=1000

# Migrate V1 config → V2 YAML (dry-run, generates output)
php bin/console micka17:typesense:migrate-config
php bin/console micka17:typesense:migrate-config --output=config/packages/typesense_v2.yaml
```

### Resources

### Aliases (zero-downtime reindex)

```bash
# Create or update an alias
php bin/console micka17:typesense:aliases:upsert products products_v2

# List all aliases
php bin/console micka17:typesense:aliases:list

# Delete an alias
php bin/console micka17:typesense:aliases:delete products
```

**PHP — atomic swap:**

```php
use Micka17\TypesenseBundle\Service\AliasManager;

$previous = $aliasManager->swapAlias('products', 'products_v2');
// $previous = 'products_v1' — the old collection, safe to delete
```

> See [docs/guides/01-zero-downtime-reindex.md](docs/guides/01-zero-downtime-reindex.md) for the full workflow.

### Resources

```bash
php bin/console micka17:typesense:synonym-sets:apply
php bin/console micka17:typesense:presets:apply
php bin/console micka17:typesense:stemming:apply
php bin/console micka17:typesense:analytics:rules:apply
php bin/console micka17:typesense:nl-search-models:apply
php bin/console micka17:typesense:conversation-models:apply
php bin/console micka17:typesense:curation-sets:apply

# List resources
php bin/console micka17:typesense:presets:list
php bin/console micka17:typesense:analytics:rules:list
# … (one :list command per resource type)

# Delete a resource
php bin/console micka17:typesense:presets:delete my-preset
php bin/console micka17:typesense:stemming:delete fr
# … (one :delete command per resource type)

# Import a stemming dictionary from file (.json or .csv)
php bin/console micka17:typesense:stemming:import fr-verbs /path/to/dict.csv
```

---

## Admin Dashboard

The bundle ships with a Bootstrap 5 admin dashboard (no Webpack required) accessible at `/admin/typesense/`.

It covers all resource types: Collections, Presets, Synonym Sets, Curation Sets, Stemming Dictionaries, Analytics Rules, NL Search Models, Conversation Models.

To enable it, ensure the bundle routes are imported:

```yaml
# config/routes.yaml
typesense_admin:
    resource: '@TypesenseBundle/config/routes.yaml'
```

Protect the prefix in your firewall as needed:

```yaml
# config/packages/security.yaml
access_control:
    - { path: ^/admin/typesense, roles: ROLE_ADMIN }
```

---

## V1 → V2 Migration Guide

> Run `php bin/console micka17:typesense:migrate-config` to detect issues automatically.

### Breaking changes

| Area | V1 | V2 |
|------|----|----|
| PHP | ≥8.1 | **≥8.5** |
| Typesense Server | 26–29 | **≥30** |
| typesense-php | ^5.0 | **^6.0** |
| Global synonyms | `typesense.synonyms` | `typesense.synonym_sets` |
| Client property | `$client->synonyms` | `$client->synonymSets` |
| `Result` | getters (`getFound()`, `getHits()`) | **public readonly properties** (`$result->found`, `$result->hits`) |
| `Paginator` | getters (`getLastPage()`) | **virtual property hooks** (`$paginator->lastPage`) |

### Step 1 — Upgrade PHP and dependencies

```bash
# Requires PHP 8.5+
composer require micka-17/typesense-bundle:^2.0
```

### Step 2 — Migrate global synonyms

**Before (V1):**
```yaml
typesense:
    synonyms:
        - { id: size-synonyms, synonyms: [large, big, huge] }
```

**After (V2):**
```yaml
typesense:
    synonym_sets:
        my-global-set:
            items:
                size-synonyms:
                    synonyms: [large, big, huge]
```

### Step 3 — Update API key scopes

If your Typesense API keys include `synonyms:*`, replace with `synonym_sets:*`.

### Step 4 — Update Result / Paginator usage

```php
// V1
$result->getFound();
$result->getHits();
$paginator->getLastPage();
$paginator->getItems();

// V2
$result->found;
$result->hits;
$paginator->lastPage;
$paginator->items;
```

### Step 5 — Migrate collection overrides to curation_sets

Collection-level overrides (`/collections/{name}/overrides`) still work in Typesense 30 but the new recommended approach uses global `curation_sets`. Migrate progressively:

```yaml
typesense:
    curation_sets:
        my-set:
            items:
                promote-best:
                    rule: { query: laptop }
                    includes:
                        - { id: 'product-1', position: 1 }
```

### Step 6 — Re-sync

```bash
php bin/console micka17:typesense:sync
php bin/console micka17:typesense:doctor
```

---

## Advanced Guides

| Guide | Description |
|---|---|
| [Zero-downtime reindex](docs/guides/01-zero-downtime-reindex.md) | Alias workflow — swap collections without search interruption |
| [Vector search & auto-embedding](docs/guides/02-vector-search-auto-embedding.md) | Built-in models, OpenAI, hybrid search, MMR diversification |
| [Conversational RAG search](docs/guides/03-conversational-rag-search.md) | Multi-turn chat with LLM-generated answers from your data |
| [Analytics pipeline](docs/guides/04-analytics-pipeline.md) | Track queries, clicks, and build a popular-searches feed |
| [Search-as-you-type](docs/guides/05-search-as-you-type.md) | Autocomplete UI with a Symfony JSON endpoint (~20 lines JS) |
| [Search parameters reference](docs/reference/search-parameters.md) | All Typesense search parameters including V30+ additions |

---

## Contributing

Pull requests are welcome. Please run the test suite and PHPStan before submitting:

```bash
composer test      # vendor/bin/phpunit
composer phpstan   # vendor/bin/phpstan analyse --memory-limit=512M
```

All tests must pass and PHPStan level 6 must report 0 errors.

## License

MIT — see [LICENSE](LICENSE).
