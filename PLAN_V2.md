# Plan V2 — micka-17/typesense-bundle

> Objectif : publier `2.0.0` compatible PHP 8.5, Symfony 7.4/8.0, Typesense Server ≥ 30, typesense-php ^6.0.
> Date de rédaction : 2026-05-07

---

## Compatibilité cible

| Composant            | Version V1 (actuel) | Version V2 cible     |
|----------------------|---------------------|----------------------|
| PHP                  | >= 8.4              | **>= 8.5**           |
| Symfony              | ^7.4 \| ^8.0        | ^7.4 \| ^8.0         |
| Typesense Server     | >= 30               | >= 30                |
| typesense-php        | ^6.0                | ^6.0                 |
| Doctrine ORM         | ^3.0                | ^3.0                 |
| PHPUnit              | ^13.0               | ^13.0                |
| PHPStan              | ^2.1                | ^2.1 (level 6)       |

---

## État actuel — diagnostic rapide

### Ce qui est implémenté et solide
- `TypesenseClient` (324 lignes) : façade complète, retry exponential backoff, cluster
- `TypesenseManager` (214 lignes) : orchestration collections + entités
- `SchemaGenerator` (142 lignes) : génère les schémas v30 depuis les attributs
- `TypesenseNormalizer` (130 lignes) : normalisation Doctrine → document
- `SynonymSetManager` (65 lignes) + `CurationSetManager` (57 lignes) : CRUD complet
- `TypesenseErrorTracker` (94 lignes) : logging multi-niveau
- `Configuration.php` (173 lignes) : arbre YAML complet
- `FinderService` (79 lignes) : search, multiSearch, preset, paginate
- `TypesenseField` attribute : tous les champs v30 présents (references, vectors, embed…)

### Ce qui est squelette / incomplet
| Fichier | Lignes | Problème |
|---------|--------|----------|
| `AnalyticsManager` | 31 | Manque : `listRules`, `retrieveRule`, `updateRule`, `deleteRule` |
| `ConversationManager` | 22 | Manque : `retrieveModel`, `listModels`, `updateModel`, `deleteModel` |
| `NaturalLanguageSearchManager` | 22 | Manque : full CRUD |
| `StemmingManager` | 21 | Manque : `listDictionaries`, `retrieveDictionary`, `deleteDictionary` |
| `PresetManager` | 21 | Manque : `listPresets`, `retrievePreset`, `deletePreset` |
| `FinderService` | 79 | Manque : Union Search, MMR/diversification, conversational search |
| `TypesenseNormalizer` | 130 | Manque : gestion vecteurs, champs embedded, références |

### Ce qui est totalement absent
- CI/CD (GitHub Actions)
- `phpstan.neon`
- Commandes list/delete pour chaque ressource
- Commande `migrate-config` (V1 → V2)
- Dashboard admin complet (CRUD sur toutes les ressources)
- Guide de migration V1 → V2
- Tests pour les commandes
- Tests pour `TypesenseNormalizer`
- Tests pour le dashboard

---

## Phase 1 — Fondations & standards (non-fonctionnel)

**Objectif :** base propre avant tout développement.

### 1.1 Nettoyage repository
- [ ] `coverage/` → `.gitignore` permanent
- [ ] `public/build/` → `.gitignore` permanent
- [ ] Retirer `analyse_projet.txt`, `to-do.md` (remplacé par ce fichier)
- [ ] Valider `.gitignore` couvre `vendor/`, `*.cache`, `*.log`

### 1.2 composer.json
```json
"require": {
    "php": ">=8.5",
    "symfony/framework-bundle": "^7.4 || ^8.0",
    "symfony/config": "^7.4 || ^8.0",
    "symfony/dependency-injection": "^7.4 || ^8.0",
    "symfony/console": "^7.4 || ^8.0",
    "doctrine/orm": "^3.0",
    "doctrine/doctrine-bundle": "^2.14 || ^3.0",
    "typesense/typesense-php": "^6.0",
    "symfony/http-client": "^7.4 || ^8.0"
},
"require-dev": {
    "phpunit/phpunit": "^13.0",
    "symfony/phpunit-bridge": "^7.4 || ^8.0",
    "phpstan/phpstan": "^2.1",
    "phpspec/prophecy-phpunit": "^2.0"
},
"scripts": {
    "test": "vendor/bin/phpunit",
    "phpstan": "vendor/bin/phpstan analyse",
    "validate-config": "composer validate --strict"
}
```
**Note :** `symfony/webpack-encore-bundle` retiré des dépendances hard — le dashboard utilisera des assets CDN ou sera optionnel.

### 1.3 phpstan.neon
```neon
parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - src/Controller/Admin
    ignoreErrors: []
```

### 1.4 CI/CD — `.github/workflows/ci.yml`
```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.5']
        symfony: ['7.4', '8.0']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '${{ matrix.php }}' }
      - run: composer install --no-interaction
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse
```

---

## Phase 2 — Modernisation PHP 8.5

**Objectif :** exploiter les fonctionnalités PHP 8.4/8.5 pour rendre le code plus expressif et performant.

### 2.1 `readonly` classes pour les DTOs
```php
// Result.php — avant
class Result {
    private array $data;
    public function __construct(array $data) { $this->data = $data; }
}

// Result.php — après (PHP 8.4+ readonly avec property hooks)
readonly class Result {
    public int $total {
        get => $this->data['found'] ?? 0;
    }
    public function __construct(private array $data) {}
}
```

### 2.2 `readonly` properties sur les services
```php
// Avant
class TypesenseClient {
    private Client $client;
    private bool $clusterEnabled;
}

// Après — asymmetric visibility PHP 8.4+
class TypesenseClient {
    private readonly Client $client;
    public private(set) bool $clusterEnabled = false;
}
```

### 2.3 Property hooks dans `Paginator`
```php
class Paginator {
    public int $totalPages {
        get => (int) ceil($this->total / $this->perPage);
    }
    public bool $hasNextPage {
        get => $this->page < $this->totalPages;
    }
}
```

### 2.4 Lazy objects pour services coûteux
```php
// TypesenseExtension.php — initialisation lazy du client
$container->register(TypesenseClient::class)
    ->setLazy(true);  // PHP 8.4 lazy ghosts
```

### 2.5 `array_find()` / `array_all()` dans le normalizer
```php
// Avant
$hasIdField = array_filter($fields, fn($f) => $f['name'] === 'id') !== [];

// Après PHP 8.4+
$hasIdField = array_any($fields, fn($f) => $f['name'] === 'id');
```

---

## Phase 3 — Compléter les managers squelettes

**Objectif :** chaque manager dispose d'un CRUD complet + méthode `applyConfigured*`.

### 3.1 `AnalyticsManager` — compléter
```php
class AnalyticsManager {
    // Existant
    public function applyConfiguredRules(array $rules): array;
    public function createEvent(array $event): array;

    // À ajouter
    public function listRules(): array;
    public function retrieveRule(string $name): array;
    public function updateRule(string $name, array $rule): array;
    public function deleteRule(string $name): array;
}
```

### 3.2 `ConversationManager` — compléter
```php
class ConversationManager {
    // Existant
    public function applyConfiguredModels(array $models): array;

    // À ajouter
    public function listModels(): array;
    public function retrieveModel(string $id): array;
    public function updateModel(string $id, array $config): array;
    public function deleteModel(string $id): array;
}
```

### 3.3 `NaturalLanguageSearchManager` — compléter
```php
class NaturalLanguageSearchManager {
    public function applyConfiguredModels(array $models): array;
    public function listModels(): array;
    public function retrieveModel(string $id): array;
    public function updateModel(string $id, array $config): array;
    public function deleteModel(string $id): array;
}
```

### 3.4 `StemmingManager` — compléter
```php
class StemmingManager {
    public function applyConfiguredDictionaries(array $dictionaries): array;
    public function listDictionaries(): array;
    public function retrieveDictionary(string $name): array;
    public function deleteDictionary(string $name): array;
    // Bonus: import depuis fichier local
    public function importFromFile(string $name, string $filePath): array;
}
```

### 3.5 `PresetManager` — compléter
```php
class PresetManager {
    public function applyConfiguredPresets(array $presets): array;
    public function listPresets(): array;
    public function retrievePreset(string $name): array;
    public function deletePreset(string $name): array;
}
```

---

## Phase 4 — Commandes manquantes

**Objectif :** chaque resource a ses commandes list/apply/delete cohérentes.

### Tableau des commandes à ajouter

| Commande | Priorité |
|----------|----------|
| `micka17:typesense:analytics:rules:list` | Haute |
| `micka17:typesense:analytics:rules:delete <name>` | Haute |
| `micka17:typesense:conversations:list` | Moyenne |
| `micka17:typesense:conversations:delete <id>` | Moyenne |
| `micka17:typesense:nl-search-models:list` | Moyenne |
| `micka17:typesense:nl-search-models:delete <id>` | Moyenne |
| `micka17:typesense:stemming:list` | Moyenne |
| `micka17:typesense:stemming:delete <name>` | Moyenne |
| `micka17:typesense:stemming:import <name> <file>` | Basse |
| `micka17:typesense:presets:list` | Moyenne |
| `micka17:typesense:presets:delete <name>` | Moyenne |
| `micka17:typesense:migrate-config` | Haute |

### `micka17:typesense:migrate-config`
Commande de migration V1 → V2 :
- Lit la config existante
- Détecte les clés dépréciées (`synonyms`, anciens `overrides`)
- Génère le bloc YAML équivalent en V2
- Affiche un rapport diff + instructions

---

## Phase 5 — FinderService avancé

**Objectif :** couvrir les fonctionnalités de recherche v28-v30.

### 5.1 Union Search
```php
/**
 * Recherche sur plusieurs collections avec déduplication.
 * @param array{searches: array<array<string,mixed>>, union?: array<string,mixed>} $params
 */
public function unionSearch(array $params): array;
```

### 5.2 MMR / Diversification
```php
/**
 * Pass-through propre pour les paramètres MMR Typesense.
 * Paramètres : mmr_lambda, mmr_embedding_field
 */
public function searchWithDiversification(string $collection, array $params): Result;
```

### 5.3 Recherche conversationnelle
```php
/**
 * Recherche avec contexte conversationnel (RAG).
 * Utilise ConversationManager en interne.
 */
public function conversationalSearch(string $collection, array $params, ?string $conversationModelId = null): Result;
```

### 5.4 Natural Language Query
```php
/**
 * Recherche en langage naturel via un modèle NL configuré.
 * Paramètre Typesense : natural_language_query
 */
public function naturalLanguageSearch(string $collection, string $query, array $extraParams = []): Result;
```

---

## Phase 6 — TypesenseNormalizer amélioré

**Objectif :** gérer correctement tous les types de champs v30.

### 6.1 Champs vectoriels embedded
```php
// Détecter les champs avec embed != null
// Ne pas normaliser leur valeur (Typesense le fait côté serveur)
// Vérifier que le champ ne contient pas de vecteur brut en doublé
```

### 6.2 Références JOINs
```php
// Champ avec reference: 'autre_collection.id'
// La valeur doit être l'ID de l'entité référencée (string)
// Si la propriété contient un objet, appeler getId() automatiquement
```

### 6.3 Objets imbriqués complexes
```php
// Champs de type 'object' ou 'object[]'
// Normalisation récursive avec support des attributs TypesenseField imbriqués
// Gestion des nullables + optional
```

---

## Phase 7 — SchemaBuilder & attributs

**Objectif :** valider que tous les champs de `TypesenseField` sont correctement traduits en schéma.

### Champs à vérifier dans `SchemaBuilder::buildFieldSchema()`
| Attribut TypesenseField | Champ schéma Typesense | Statut actuel |
|-------------------------|------------------------|---------------|
| `reference` | `reference` | À vérifier |
| `asyncReference` | `async_reference` | À vérifier |
| `cascadeDelete` | `cascade_delete` | À vérifier |
| `truncate` | `truncate` | À vérifier |
| `tokenSeparators` | `token_separators` | À vérifier |
| `symbolsToIndex` | `symbols_to_index` | À vérifier |
| `embed` | `embed` | À vérifier |
| `numDim` | `num_dim` | À vérifier |
| `vecDist` | `vec_dist` | À vérifier |
| `hnswParams` | `hnsw_params` | À vérifier |

### Nouvel attribut `#[TypesenseCuration]`
```php
#[Attribute(Attribute::TARGET_CLASS)]
class TypesenseCuration {
    public function __construct(
        public string $setName,
        public string $ruleQuery,
        public array $includes = [],
        public array $excludes = [],
        public ?string $filterBy = null,
        public ?string $sortBy = null,
    ) {}
}
```

---

## Phase 8 — Tests complets

**Objectif :** PHPUnit 13 vert, couverture > 80% des services.

### Tests à créer

| Fichier de test | Ce qui doit être couvert |
|-----------------|--------------------------|
| `AnalyticsManagerTest` | listRules, retrieveRule, updateRule, deleteRule, createEvent |
| `ConversationManagerTest` | CRUD complet |
| `NaturalLanguageSearchManagerTest` | CRUD complet |
| `StemmingManagerTest` | CRUD + import fichier |
| `PresetManagerTest` | CRUD + listPresets |
| `FinderServiceTest` | search, multiSearch, union, MMR, conversational, paginate |
| `TypesenseNormalizerTest` | vecteurs, références, objets imbriqués, nullable |
| `SchemaBuilderTest` | tous les champs TypesenseField → schéma JSON |
| `AutoUpdateListenerTest` | postPersist, postUpdate, preRemove |
| `TypesenseExtensionTest` | chargement services depuis config |
| Commandes | via `CommandTester` pour chaque command |

---

## Phase 9 — Dashboard admin

**Objectif :** interface web complète sans dépendance webpack.

### Architecture
- Assets via CDN (Bootstrap 5 + Bootstrap Icons) — retirer `webpack-encore-bundle` des deps
- Pages : Collections, Synonym Sets, Curation Sets, Presets, Analytics, Stemming, NL Models, Conversations
- Actions sécurisées : reindex, recreate, apply config, delete ressource
- CSRF sur toutes les actions destructrices (token `typesense_admin_action`)
- Pagination sur les listes longues

### Routes
```
/admin/typesense/               → dashboard overview
/admin/typesense/collections    → liste + actions
/admin/typesense/synonym-sets   → liste + apply + delete
/admin/typesense/curation-sets  → liste + apply + delete
/admin/typesense/presets        → liste + apply + delete
/admin/typesense/analytics      → liste rules + apply
/admin/typesense/stemming       → liste + apply
/admin/typesense/nl-models      → liste + apply
/admin/typesense/conversations  → liste + delete
```

---

## Phase 10 — Documentation

### README.md restructuré
```
1. Installation
2. Configuration minimale
3. Indexation d'entités (attributs)
4. Recherche (FinderService)
5. Synonym Sets
6. Curation Sets
7. Presets
8. Stemming
9. Analytics
10. Natural Language Search
11. Conversations / RAG
12. Dashboard admin
13. Commandes console
14. Guide de migration V1 → V2
15. Tableau de compatibilité
```

### Guide de migration V1 → V2
| Ancienne clé | Nouvelle clé | Action |
|--------------|--------------|--------|
| `typesense.synonyms` | `typesense.synonym_sets` | Restructurer en sets |
| Overrides collection | `typesense.curation_sets` | Migrer vers sets globaux |
| `synonyms:*` API key actions | `synonym_sets:*` | Mettre à jour les clés API |

---

## Ordre de développement recommandé

```
Sprint 1  → Phase 1 (fondations) + Phase 2 (PHP 8.5)
Sprint 2  → Phase 3 (compléter managers) + Phase 4 (commandes)
Sprint 3  → Phase 5 (FinderService) + Phase 6 (Normalizer)
Sprint 4  → Phase 7 (SchemaBuilder) + Phase 8 (tests)
Sprint 5  → Phase 9 (dashboard) + Phase 10 (docs)
Release   → tag v2.0.0, Packagist, changelog
```

---

## Definition of Done

- [ ] PHP `>=8.5` — property hooks, readonly classes, array_find utilisés
- [ ] Tous les managers ont un CRUD complet
- [ ] `FinderService` supporte union search, MMR, NL query, conversational
- [ ] `TypesenseNormalizer` gère vecteurs, références, objets imbriqués
- [ ] Tous les champs `TypesenseField` sont traduits en schéma Typesense correct
- [ ] PHPUnit 13 vert — couverture > 80%
- [ ] PHPStan level 6 vert
- [ ] GitHub Actions CI vert sur PHP 8.5 × Symfony 7.4/8.0
- [ ] Commandes list/delete pour chaque ressource
- [ ] `migrate-config` produit un rapport V1 → V2 actionnable
- [ ] Dashboard admin complet + CSRF
- [ ] README restructuré + guide de migration V1 → V2
- [ ] Tag `v2.0.0` publié sur Packagist
