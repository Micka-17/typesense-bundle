# Vector Search & Auto-Embedding

Typesense supports two embedding strategies:

| Strategy | How | When |
|---|---|---|
| **Built-in models** | Typesense embeds on ingest, no external API | Small/medium datasets, no extra cost |
| **Remote models** | OpenAI / GCP / custom endpoint | Larger models, existing OpenAI key |
| **Bring your own** | You compute the vector, store it as `float[]` | Full control, offline inference |

---

## 1. Auto-embedding with a built-in model

### Entity annotation

```php
use Micka17\TypesenseBundle\Attribute\TypesenseField;
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;

#[TypesenseIndexable(collection: 'products')]
class Product
{
    #[TypesenseField(type: 'string', sort: true)]
    private string $name;

    #[TypesenseField(type: 'string')]
    private string $description;

    // Auto-embedding: Typesense computes the vector from $name and $description
    #[TypesenseField(
        type: 'float[]',
        embed: [
            'from'         => ['name', 'description'],
            'model_config' => ['model_name' => 'ts/e5-small'],
        ],
        numDim: 384,
        vecDist: 'cosine',
    )]
    private array $embedding = [];
}
```

Available built-in models: `ts/e5-small`, `ts/e5-base`, `ts/multilingual-e5-small`, `ts/arctic-embed-xs`.

### Search by text (Typesense embeds the query automatically)

```php
$result = $finder->search('products', [
    'q'        => 'lightweight laptop for travel',
    'query_by' => 'embedding',     // the vector field
    'per_page' => 10,
]);
```

---

## 2. Auto-embedding with OpenAI

```php
#[TypesenseField(
    type: 'float[]',
    embed: [
        'from'         => ['name', 'description'],
        'model_config' => [
            'model_name' => 'openai/text-embedding-3-small',
            'api_key'    => 'YOUR_OPENAI_KEY',  // use %env(OPENAI_API_KEY)% in config
        ],
    ],
    numDim: 1536,
    vecDist: 'cosine',
)]
private array $embedding = [];
```

> Store the key in `.env`: `OPENAI_API_KEY=sk-...` and reference it via `%env(OPENAI_API_KEY)%` in your entity or configuration.

---

## 3. Bring-your-own vectors

If you compute embeddings outside Typesense (e.g. with a local model), store them directly:

```php
#[TypesenseField(type: 'float[]', numDim: 768, vecDist: 'cosine')]
private array $embedding = [];
```

In your normalizer or getter, return the pre-computed vector:

```php
public function getEmbedding(): array
{
    return $this->embeddingService->embed($this->name . ' ' . $this->description);
}
```

Use `getter: 'getEmbedding'` in the attribute if the property name differs.

---

## 4. Hybrid search (full-text + vector)

Combine keyword and semantic search in one query:

```php
$result = $finder->search('products', [
    'q'            => 'laptop',
    'query_by'     => 'name,embedding',          // text field + vector field
    'query_by_weights' => '1,2',                 // weight vector higher
    'per_page'     => 10,
]);
```

---

## 5. Vector-only search (raw vector query)

```php
$queryVector = $embeddingService->embed('lightweight laptop for travel');

$result = $finder->search('products', [
    'q'            => '*',
    'query_by'     => 'name',
    'vector_query' => 'embedding:(' . implode(',', $queryVector) . ', k:10)',
]);
```

Syntax: `field_name:([v1, v2, ...], k:N)` where `k` is the number of nearest neighbours.

---

## 6. MMR diversification (reduce near-duplicates)

```php
$result = $finder->searchWithDiversification(
    'products',
    [
        'q'            => 'laptop',
        'query_by'     => 'name,embedding',
        'vector_query' => 'embedding:(' . implode(',', $queryVector) . ', k:20)',
    ],
    mmrLambda: 0.7,          // 1.0 = max relevance, 0.0 = max diversity
    mmrEmbeddingField: 'embedding',
);
```

MMR (Maximal Marginal Relevance) re-ranks results to avoid showing 10 near-identical products.

---

## 7. HNSW index tuning

For large collections, tune the HNSW graph for better recall/speed trade-off:

```php
#[TypesenseField(
    type: 'float[]',
    numDim: 384,
    vecDist: 'cosine',
    hnswParams: ['M' => 32, 'ef_construction' => 200],
)]
private array $embedding = [];
```

| Parameter | Default | Effect |
|---|---|---|
| `M` | 16 | Graph connections per node — higher = better recall, more memory |
| `ef_construction` | 200 | Build-time search depth — higher = better quality, slower indexing |

---

## 8. Distance metrics

| `vecDist` | Use case |
|---|---|
| `cosine` | Normalized embeddings (most common) |
| `ip` (inner product) | Dot-product similarity, used by some OpenAI models |
| `l2sq` | Euclidean distance, for unnormalized vectors |
