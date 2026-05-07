# Conversational RAG Search

Typesense 30+ supports **Retrieval-Augmented Generation (RAG)**: it retrieves relevant documents from your collection and passes them to an LLM, which generates a natural-language answer.

---

## 1. Configure a conversation model

```yaml
# config/packages/typesense.yaml
typesense:
    conversation_models:
        support-bot:
            model_name: openai/gpt-4o-mini
            api_key: '%env(OPENAI_API_KEY)%'
            system_prompt: >
                You are a helpful product assistant.
                Answer based only on the provided search results.
                If the answer is not in the results, say so.
```

Apply it to Typesense:

```bash
php bin/console micka17:typesense:conversation-models:apply
```

Verify:

```bash
php bin/console micka17:typesense:conversations:list
```

---

## 2. First query

```php
use Micka17\TypesenseBundle\Service\FinderService;

class SupportController extends AbstractController
{
    public function __construct(private readonly FinderService $finder) {}

    public function ask(string $question): array
    {
        $result = $this->finder->conversationalSearch(
            collection: 'products',
            params: [
                'q'        => $question,
                'query_by' => 'name,description',
                'per_page' => 5,
            ],
            modelId: 'support-bot',
        );

        return [
            'answer'         => $result->conversationAnswer,
            'conversation_id' => $result->conversationId,
            'hits'           => $result->hits,
        ];
    }
}
```

### Result properties

| Property | Type | Description |
|---|---|---|
| `$result->conversationAnswer` | `string` | LLM-generated answer |
| `$result->conversationId` | `string` | Pass on follow-up queries |
| `$result->hits` | `array` | Raw document hits used as context |
| `$result->found` | `int` | Total matching documents |

---

## 3. Multi-turn conversation

Pass `$conversationId` back on every follow-up to maintain context:

```php
// Turn 1
$r1 = $this->finder->conversationalSearch('products', ['q' => 'best laptop for gaming'], 'support-bot');
$conversationId = $r1->conversationId;
// → "The ASUS ROG Zephyrus G16 is a top choice for gaming..."

// Turn 2 — follow-up question
$r2 = $this->finder->conversationalSearch(
    'products',
    ['q' => 'what about battery life?'],
    'support-bot',
    $conversationId,   // ← same conversation
);
// → "The ASUS ROG has a 90Wh battery rated for ~6 hours of light use..."
```

Conversation history is stored as a regular Typesense collection automatically.

---

## 4. Symfony controller — streaming JSON response

For a chat-style UI, return a JSON stream:

```php
#[Route('/api/chat', methods: ['POST'])]
public function chat(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $question       = $data['question'] ?? '';
    $conversationId = $data['conversation_id'] ?? null;

    $result = $this->finder->conversationalSearch(
        'products',
        ['q' => $question, 'query_by' => 'name,description', 'per_page' => 3],
        'support-bot',
        $conversationId,
    );

    return new JsonResponse([
        'answer'          => $result->conversationAnswer,
        'conversation_id' => $result->conversationId,
        'sources'         => array_map(
            fn(array $hit) => ['id' => $hit['document']['id'], 'name' => $hit['document']['name']],
            $result->hits,
        ),
    ]);
}
```

---

## 5. Frontend example (vanilla JS)

```html
<div id="chat"></div>
<input id="q" type="text" placeholder="Ask a question...">
<button onclick="ask()">Send</button>

<script>
let conversationId = null;

async function ask() {
    const question = document.getElementById('q').value;
    const res = await fetch('/api/chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question, conversation_id: conversationId }),
    });
    const data = await res.json();
    conversationId = data.conversation_id;

    document.getElementById('chat').innerHTML +=
        `<p><strong>Q:</strong> ${question}</p>` +
        `<p><strong>A:</strong> ${data.answer}</p>` +
        `<small>Sources: ${data.sources.map(s => s.name).join(', ')}</small><hr>`;
}
</script>
```

---

## 6. Best practices

**Token limits** — Each conversation turn appends to the context window. Set `max_tokens` or limit `per_page` to avoid exceeding the model's context limit.

**Rate limiting** — LLM API calls are slow (~1–3s). Add a debounce or loading indicator in the UI.

**Costs** — Each `conversationalSearch()` call hits the LLM API. Cache repeated identical questions if your use case allows it.

**System prompt quality** — Be explicit about what the model should and should not say. "Answer only from the provided results" prevents hallucination.

**Collection choice** — Use a collection with rich text fields (`description`, `body`, etc.) as RAG context. Sparse fields (just `name`, `sku`) produce poor answers.

---

## 7. Manage conversation models

```bash
# List
php bin/console micka17:typesense:conversations:list

# Delete (stops billing on next deploy)
php bin/console micka17:typesense:conversations:delete support-bot
```
