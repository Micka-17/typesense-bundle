# Analytics Pipeline

Typesense Analytics tracks search queries and click events, then aggregates them into separate collections you can query for insights (popular queries, no-result queries, conversion tracking).

---

## 1. Configure analytics rules

```yaml
# config/packages/typesense.yaml
typesense:
    analytics_rules:
        popular_products:
            type: popular_queries
            params:
                source:
                    collections: [products]
                destination:
                    collection: popular_queries   # auto-created by Typesense
                limit: 1000

        no_results_queries:
            type: nohits_queries
            params:
                source:
                    collections: [products]
                destination:
                    collection: no_results_queries
                limit: 500
```

Apply to Typesense:

```bash
php bin/console micka17:typesense:analytics:rules:apply
```

---

## 2. Send search events from PHP

Every search that should be tracked must include `analytics_tag` and `x-typesense-user-id`:

```php
use Micka17\TypesenseBundle\Service\FinderService;

class ProductSearchService
{
    public function __construct(private readonly FinderService $finder) {}

    public function search(string $query, string $userId): array
    {
        return $this->finder->search('products', [
            'q'             => $query,
            'query_by'      => 'name,description',
            'per_page'      => 20,
            'analytics_tag' => 'website-search',
            // Pass the user ID via a custom header (see controller below)
        ])->hits;
    }
}
```

To pass `x-typesense-user-id`, set it as a query parameter:

```php
$result = $finder->search('products', [
    'q'                       => $query,
    'query_by'                => 'name,description',
    'analytics_tag'           => 'website-search',
    'x-typesense-user-id'     => $userId,    // anonymized or hashed
]);
```

---

## 3. Send click/conversion events

```php
use Micka17\TypesenseBundle\Service\AnalyticsManager;

class ProductController extends AbstractController
{
    public function __construct(private readonly AnalyticsManager $analytics) {}

    #[Route('/product/{id}/click')]
    public function trackClick(string $id, Request $request): JsonResponse
    {
        $this->analytics->createEvent([
            'type'          => 'click',
            'name'          => 'product_click',
            'data'          => [
                'q'                   => $request->query->get('q', ''),
                'doc_id'              => $id,
                'user_id'             => $this->getUserId(),
                'position'            => (int) $request->query->get('position', 1),
            ],
        ]);

        return new JsonResponse(['ok' => true]);
    }
}
```

Event types:

| Type | Description |
|---|---|
| `click` | User clicked a search result |
| `conversion` | User purchased / signed up after search |
| `custom` | Any custom event |

---

## 4. Integrate with a Symfony search form

Capture queries automatically using a form event subscriber:

```php
// src/EventSubscriber/SearchAnalyticsSubscriber.php
class SearchAnalyticsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly FinderService $finder) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->query->has('q') && str_starts_with($request->getPathInfo(), '/search')) {
            // analytics_tag is sent inline with the search query (see §2 above)
        }
    }
}
```

Simpler: just add `analytics_tag` to every `FinderService::search()` call in your app.

---

## 5. Query the aggregated data

Popular queries are stored as a regular Typesense collection. Query them with `FinderService`:

```php
$popular = $finder->search('popular_queries', [
    'q'        => '*',
    'query_by' => 'q',
    'sort_by'  => 'count:desc',
    'per_page' => 20,
]);

foreach ($popular->hits as $hit) {
    echo $hit['document']['q'] . ': ' . $hit['document']['count'] . "\n";
}
```

---

## 6. Manage analytics rules

```bash
# List rules
php bin/console micka17:typesense:analytics:rules:list

# Delete a rule
php bin/console micka17:typesense:analytics:rules:delete no_results_queries
```

---

## 7. Export for BI / dashboards

Export the aggregated collections as JSONL and load into your BI tool:

```bash
# Export popular queries
php bin/console micka17:typesense:documents:export popular_queries \
    --output=/var/exports/popular_queries.jsonl

# Export no-result queries (to fix search gaps)
php bin/console micka17:typesense:documents:export no_results_queries \
    --output=/var/exports/no_results.jsonl
```

Schedule this as a cron job for daily exports.
