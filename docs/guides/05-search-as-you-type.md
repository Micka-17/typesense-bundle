# Search-as-you-type

Real-time search with Typesense and Symfony — no Webpack required.

---

## 1. Symfony controller — JSON search endpoint

```php
// src/Controller/SearchController.php
use Micka17\TypesenseBundle\Service\FinderService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(private readonly FinderService $finder) {}

    #[Route('/api/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));

        if ($q === '') {
            return new JsonResponse(['hits' => []]);
        }

        $result = $this->finder->search('products', [
            'q'                => $q,
            'query_by'         => 'name,description',
            'highlight_fields' => 'name',
            'per_page'         => 5,
            'typo_tokens_threshold' => 1,
        ]);

        $hits = array_map(fn(array $hit) => [
            'id'        => $hit['document']['id'],
            'name'      => $hit['document']['name'],
            'highlight' => $hit['highlights'][0]['snippet'] ?? $hit['document']['name'],
            'price'     => $hit['document']['price'] ?? null,
        ], $result->hits);

        return new JsonResponse(['hits' => $hits]);
    }
}
```

---

## 2. Twig template with inline JS (~20 lines)

```twig
{# templates/search/autocomplete.html.twig #}
<div style="position:relative; max-width:400px;">
    <input
        id="search-input"
        type="search"
        placeholder="Search products…"
        autocomplete="off"
        class="form-control"
    >
    <ul id="search-results"
        class="list-group shadow-sm"
        style="position:absolute; z-index:1000; width:100%; display:none;">
    </ul>
</div>

<script>
(function () {
    const input   = document.getElementById('search-input');
    const results = document.getElementById('search-results');
    let timer;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (!q) { results.style.display = 'none'; return; }

        timer = setTimeout(async () => {
            const res  = await fetch(`/api/search?q=${encodeURIComponent(q)}`);
            const data = await res.json();

            results.innerHTML = data.hits.map(h =>
                `<li class="list-group-item list-group-item-action" onclick="location.href='/products/${h.id}'">
                    ${h.highlight}
                    ${h.price ? `<span class="float-end text-muted small">${h.price} €</span>` : ''}
                </li>`
            ).join('');

            results.style.display = data.hits.length ? 'block' : 'none';
        }, 200);  // 200ms debounce
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target)) results.style.display = 'none';
    });
})();
</script>
```

---

## 3. With Symfony UX Autocomplete (optional)

If you already use Symfony UX, you can wire the search endpoint to a `UxAutocomplete` component:

```bash
composer require symfony/ux-autocomplete
```

```php
// src/Form/ProductSearchType.php
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;

#[AsEntityAutocompleteField]
class ProductAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class'         => Product::class,
            'searcher'      => ProductSearcher::class,
            'placeholder'   => 'Search products…',
        ]);
    }
}
```

Then implement `ProductSearcher` to call `FinderService` and return matching entities.

---

## 4. Faceted search UI

Extend the endpoint to return facets for a filter sidebar:

```php
$result = $this->finder->search('products', [
    'q'         => $q,
    'query_by'  => 'name',
    'facet_by'  => 'category,brand',
    'per_page'  => 20,
]);

return new JsonResponse([
    'hits'        => ...,
    'facetCounts' => $result->facetCounts,
    'total'       => $result->found,
]);
```

---

## 5. Pagination

```php
use Micka17\TypesenseBundle\Service\FinderService;

$paginator = $finder->searchAndPaginate(
    'products',
    ['q' => $q, 'query_by' => 'name'],
    page: (int) $request->query->get('page', 1),
    perPage: 20,
);

// In Twig:
// {{ paginator.total }} results
// {{ paginator.currentPage }} / {{ paginator.lastPage }}
// {% if paginator.hasNextPage %} ... {% endif %}
```

---

## 6. Performance tips

| Tip | Impact |
|---|---|
| `per_page: 5` for autocomplete | Reduces response size and Typesense CPU |
| `query_by_weights` to prioritize `name` over `description` | Better relevance, no extra cost |
| Debounce 150–250ms | Avoids a request per keystroke |
| `prefix: true` (default) | Matches partial words at the end — essential for SAYT |
| `typo_tokens_threshold: 1` | Only applies typo correction when needed |
| `highlight_fields: name` | Only compute highlights for the displayed field |
