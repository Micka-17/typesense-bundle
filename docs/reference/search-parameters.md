# Search Parameters Reference

Complete reference for parameters accepted by `FinderService::search()` and passed through to Typesense.  
All parameters are passed as keys in the `$params` array.

---

## Core

| Parameter | Type | Description |
|---|---|---|
| `q` | string | Query string. Use `*` to match all documents. |
| `query_by` | string | Comma-separated field names to search. |
| `query_by_weights` | string | Relative weights for `query_by` fields, e.g. `2,1`. |
| `prefix` | bool/string | Enable prefix matching (default: `true`). Comma-separated per field: `true,false`. |
| `infix` | string | Enable infix search per field: `always`, `fallback`, `off`. |
| `per_page` | int | Results per page (default: 10, max: 250). |
| `page` | int | Page number (1-indexed). |
| `filter_by` | string | Filter expression, e.g. `price:<100 && stock:>0`. |
| `sort_by` | string | Sort expression, e.g. `price:asc` or `_text_match:desc,price:asc`. |
| `facet_by` | string | Comma-separated facet fields. |
| `max_facet_values` | int | Max facet values returned per field (default: 10). |
| `facet_query` | string | Filter facet values by prefix. |

---

## Typo tolerance

| Parameter | Type | Description |
|---|---|---|
| `num_typos` | int/string | Max typos allowed (default: `2`). Per field: `1,2`. |
| `min_len_1typo` | int | Minimum word length for 1-typo correction (default: 4). |
| `min_len_2typo` | int | Minimum word length for 2-typo correction (default: 7). |
| `typo_tokens_threshold` | int | Number of results below which typo correction is applied. |
| `drop_tokens_threshold` | int | Number of results below which tokens are dropped. |
| `enable_typos_for_alpha_numerical_tokens` | bool | Apply typos to alphanumeric tokens (e.g. "iphone12"). *(v27+)* |

---

## Relevance & ranking

| Parameter | Type | Description |
|---|---|---|
| `text_match_type` | string | `max_score` (default) or `max_weight` or `sum_score`. *(v27+)* |
| `bucket_size` | int | Group results by text match score buckets before applying sort. *(v28+)* |
| `ranking_tokens` | int | Number of tokens used for text match ranking. |
| `prioritize_exact_match` | bool | Boost exact matches (default: `true`). |
| `prioritize_token_position` | bool | Boost results where query tokens appear earlier. |
| `prioritize_num_matching_fields` | bool | Boost results matching more fields. |

---

## Grouping

| Parameter | Type | Description |
|---|---|---|
| `group_by` | string | Field name to group results by. |
| `group_limit` | int | Max results per group. |
| `group_missing_values` | bool | Include documents with missing group field. |
| `group_max_candidates` | int | Max candidates per group for accurate counts. *(v30+)* |

---

## Pinning & overrides

| Parameter | Type | Description |
|---|---|---|
| `pinned_hits` | string | Force specific docs to the top: `id1:1,id2:2`. |
| `hidden_hits` | string | Exclude specific docs: `id1,id2`. |
| `override_tags` | string | Comma-separated override tags to apply. |
| `enable_overrides` | bool | Enable/disable curation overrides (default: `true`). |
| `filter_curated_hits` | bool | Apply `filter_by` to pinned/curated hits. *(v27+)* |

---

## Highlighting

| Parameter | Type | Description |
|---|---|---|
| `highlight_fields` | string | Fields to highlight (default: all `query_by` fields). |
| `highlight_full_fields` | string | Fields to return full content with highlights. |
| `highlight_affix_num_tokens` | int | Tokens of context before/after match (default: 4). |
| `highlight_start_tag` | string | HTML tag for highlight start (default: `<mark>`). |
| `highlight_end_tag` | string | HTML tag for highlight end (default: `</mark>`). |
| `snippet_threshold` | int | Min tokens for a snippet vs. full field (default: 30). |

---

## Field inclusion / exclusion

| Parameter | Type | Description |
|---|---|---|
| `include_fields` | string | Comma-separated fields to return. |
| `exclude_fields` | string | Comma-separated fields to exclude. |

---

## Sorting — special functions (v28+)

| Expression | Description |
|---|---|
| `_rand()` | Random order |
| `_rand(42)` | Seeded random (deterministic) |
| `_geo(lat,lon):asc` | Geospatial proximity sort |
| `decay(gauss, field, origin, scale, offset, decay_factor):desc` | Gaussian decay — boosts near origin |
| `decay(linear, ...)` | Linear decay |
| `decay(exp, ...)` | Exponential decay |

---

## Vector search (v27+)

| Parameter | Type | Description |
|---|---|---|
| `vector_query` | string | `field:([ v1, v2, ... ], k:N)` or `field:(id: doc_id, k:N)` |
| `distance_threshold` | float | Maximum distance for inner-product vector queries. *(v28+)* |

---

## MMR diversification (v30+)

| Parameter | Type | Description |
|---|---|---|
| `mmr_lambda` | float | Balance relevance/diversity: `1.0` = pure relevance, `0.0` = pure diversity. |
| `mmr_embedding_field` | string | Vector field to use for diversity computation. |

Pass via `FinderService::searchWithDiversification()`:

```php
$finder->searchWithDiversification('products', $params, mmrLambda: 0.7, mmrEmbeddingField: 'embedding');
```

---

## Natural language search (v29+)

| Parameter | Type | Description |
|---|---|---|
| `natural_language_query` | string | Free-text intent query (translated server-side to filters). |
| `nl_search_model_id` | string | ID of the NL search model to use. |

Pass via `FinderService::naturalLanguageSearch()`:

```php
$finder->naturalLanguageSearch('products', 'laptops under 1000 euros with good battery', 'products-nl');
```

---

## Conversational search (v27+)

| Parameter | Type | Description |
|---|---|---|
| `conversation` | bool | Enable RAG-style answer generation (default: `false`). |
| `conversation_model_id` | string | ID of the conversation model. |
| `conversation_id` | string | Existing conversation ID for multi-turn context. |

Pass via `FinderService::conversationalSearch()`:

```php
$finder->conversationalSearch('products', $params, 'support-bot', $conversationId);
```

---

## Union search (v28+)

Searched via `FinderService::unionSearch()` — merges results from multiple collections:

```php
$finder->unionSearch([
    ['collection' => 'products', 'q' => 'laptop', 'query_by' => 'name'],
    ['collection' => 'articles', 'q' => 'laptop', 'query_by' => 'title'],
], ['per_page' => 10]);
```

Each hit includes `_collection` indicating its source collection.

| Parameter | Type | Description |
|---|---|---|
| `remove_duplicates` | bool | Deduplicate near-identical results across collections. *(v30+)* |

---

## Synonyms

| Parameter | Type | Description |
|---|---|---|
| `enable_synonyms` | bool | Enable synonym expansion (default: `true`). *(v27+)* |
| `synonym_prefix` | bool | Apply synonyms on prefix matches. *(v27+)* |
| `synonym_num_typos` | int | Typo tolerance for synonym matching. *(v27+)* |

---

## Analytics

| Parameter | Type | Description |
|---|---|---|
| `analytics_tag` | string | Tag this search for analytics aggregation. |
| `enable_analytics` | bool | Include this query in analytics (default: `true`). *(v27+)* |
| `x-typesense-user-id` | string | User ID for click analytics correlation. |

---

## Caching & lazy evaluation

| Parameter | Type | Description |
|---|---|---|
| `use_cache` | bool | Use query cache (default: `true`). |
| `cache_ttl` | int | Cache TTL in seconds. |
| `enable_lazy_filter` | bool | Defer expensive numeric filters for faster initial results. *(v27+)* |
| `max_filter_by_candidates` | int | Max candidates for fuzzy `filter_by` expansion. *(v28+)* |

---

## Pagination — advanced

| Parameter | Type | Description |
|---|---|---|
| `limit_hits` | int | Hard cap on total hits returned across pages. |
| `offset` | int | Skip N results (alternative to `page`). |

---

## Geospatial

| Parameter | Type | Description |
|---|---|---|
| `filter_by` with `(lat,lon, radius km)` | string | Circle filter: `location:(48.8566,2.3522, 10 km)` |
| `filter_by` with polygon | string | Polygon filter: `location:(lat1,lon1, lat2,lon2, ...)` *(v28+)* |
| `geo_precision` | string | Precision override for geo filters. |
