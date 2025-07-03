# TypesenseBundle Documentation

Bienvenue dans la documentation du TypesenseBundle pour Symfony.

## Introduction

Le TypesenseBundle est un bundle Symfony qui facilite l'intégration de Typesense, un moteur de recherche open-source rapide et facile à utiliser, dans vos applications Symfony.

## Installation

Pour installer le bundle, utilisez Composer :

```bash
composer require micka-17/typesense-bundle
```

Ensuite, configurez le bundle dans votre fichier `config/bundles.php` :

```php
return [
    // ...
    Typesense\TypesenseBundle\TypesenseBundle::class => ['all' => true],
];
```

## Configuration

Configurez Typesense dans votre fichier `config/packages/typesense.yaml` :

```yaml
typesense:
    api_key: '%env(TYPENSE_API_KEY)%'
    nodes:
        - host: '%env(TYPENSE_HOST)%'
          port: '%env(TYPENSE_PORT)%'
          protocol: '%env(TYPENSE_PROTOCOL)%'
    
    # Configuration des entités indexables
    indexable_entities:
        - App\Entity\Product
        - App\Entity\Category
        - App\Entity\User

    # Configuration des synonymes globaux
    synonyms:
        - { id: 'size-synonyms', synonyms: ['large', 'big', 'huge'] }
        - { id: 'color-synonyms', root: 'primary_color', synonyms: ['red', 'blue', 'green'] }
    
    # Configuration de l'auto-update (activé par défaut)
    auto_update: true
    
    # Configuration du tracking des erreurs
    error_tracking:
        enabled: false
        log_level: 'error'
        track_node_errors: false
        node_error_fields: []
```

### Configuration des Entités Indexables

La configuration `indexable_entities` est essentielle pour le bon fonctionnement du bundle. Elle définit la liste des entités qui seront indexées dans Typesense.

#### 1. Sécurité
- Limite les entités qui peuvent être indexées dans Typesense
- Empêche l'indexation accidentelle d'entités non désirées
- Assure que seules les entités spécifiquement configurées peuvent être manipulées par le bundle

#### 2. Performance
- Optimise la gestion mémoire en ne chargeant que les entités nécessaires
- Accélère les opérations d'indexation en ne traitant que les entités définies
- Réduit le risque de surcharge du système en limitant le nombre d'entités gérées

#### 3. Auto-Update
- Les entités listées dans `indexable_entities` bénéficient automatiquement de l'auto-update
- Les modifications des entités sont automatiquement synchronisées avec Typesense
- Les événements Doctrine (`postPersist`, `postUpdate`, `preRemove`) sont automatiquement gérés

#### 4. Commandes de Gestion
- Les commandes `micka17:typesense:manage` ne peuvent être utilisées que sur les entités listées
- Assure que les opérations de gestion sont limitées aux entités configurées
- Prévient les erreurs potentielles sur des entités non configurées

#### 5. Bonnes Pratiques
1. **Listez explicitement toutes vos entités indexables** :
```yaml
indexable_entities:
    - App\Entity\Product
    - App\Entity\Category
    - App\Entity\User
```

2. **Évitez les caractères génériques** :
```yaml
# À éviter
indexable_entities:
    - App\Entity\*
```

3. **Mettez à jour la liste lors de l'ajout d'une nouvelle entité** :
```yaml
# Ajoutez chaque nouvelle entité
indexable_entities:
    - App\Entity\Product
    - App\Entity\Category
    - App\Entity\User
    - App\Entity\NewEntity
```

4. **Utilisez les espaces de noms complets** :
```yaml
# Utilisez toujours les espaces de noms complets
indexable_entities:
    - App\Entity\Product  # Correct
    - Product            # Incorrect
```

#### 6. Exemple Complet
```yaml
typesense:
    # Configuration de base
    api_key: '%env(TYPENSE_API_KEY)%'
    nodes:
        - host: '%env(TYPENSE_HOST)%'
          port: '%env(TYPENSE_PORT)%'
          protocol: '%env(TYPENSE_PROTOCOL)%'
    
    # Entités à indexer
    indexable_entities:
        - App\Entity\Product
        - App\Entity\Category
        - App\Entity\User
        - App\Entity\Article
        - App\Entity\Comment

    # Synonymes globaux
    synonyms:
        - { id: 'size-synonyms', synonyms: ['large', 'big', 'huge'] }
        - { id: 'color-synonyms', root: 'primary_color', synonyms: ['red', 'blue', 'green'] }
    
    # Auto-update activé par défaut
    auto_update: true
    
    # Tracking des erreurs
    error_tracking:
        enabled: false
        log_level: 'error'
        track_node_errors: false
        node_error_fields: []
```

### Que se passe-t-il si une entité n'est pas listée ?

1. **Auto-Update** : Les modifications des entités non listées ne seront pas synchronisées avec Typesense
2. **Commandes** : Les commandes `micka17:typesense:manage` ne fonctionneront pas sur ces entités
3. **Sécurité** : Les tentatives d'indexation d'entités non listées seront rejetées
4. **Performance** : Les entités non listées ne seront pas chargées en mémoire inutilement

### Conseils pour la Maintenance

1. **Documentation** : Documentez clairement les entités indexables dans votre projet
2. **Tests** : Ajoutez des tests pour vérifier que toutes les entités indexables sont correctement configurées
3. **Monitoring** : Surveillez les logs pour détecter les tentatives d'indexation d'entités non listées
4. **Mises à jour** : Mettez à jour la liste des entités indexables lors de refactoring ou d'ajout de nouvelles entités
5. **Sécurité** : Revoyez régulièrement la liste des entités indexables pour vous assurer qu'aucune entité sensible n'est exposée inutilement

## Utilisation

### FinderService

Le `FinderService` est le service principal pour effectuer des recherches. Il prend en charge la recherche sur une seule collection ainsi que la recherche multi-collections.

```php
use Micka17\TypesenseBundle\Service\FinderService;

class YourService
{
    public function __construct(private FinderService $finderService)
    {
    }

    public function searchProducts(string $query)
    {
        return $this->finderService->search('products', ['q' => $query]);
    }

    public function searchMulti(string $query)
    {
        $searchRequests = [
            'searches' => [
                ['collection' => 'products', 'q' => $query],
                ['collection' => 'categories', 'q' => $query],
            ]
        ];

        return $this->finderService->multiSearch($searchRequests);
    }
}
```

### TypesenseClient

Le `TypesenseClient` est utilisé pour les interactions directes avec l'API Typesense :

```php
use Micka17\TypesenseBundle\Service\TypesenseClient;

class YourService
{
    public function __construct(private TypesenseClient $typesenseClient)
    {
    }

    public function createCollection(array $collectionConfig)
    {
        return $this->typesenseClient->getOperations()->collections->create($collectionConfig);
    }
}
```

## Contribution

Les contributions sont les bienvenues ! N'hésitez pas à créer des issues ou des pull requests.

## Support

Pour toute question ou problème, ouvrez une issue sur le repository GitHub du projet.

## Indexation des Entités

Pour indexer une entité dans Typesense, vous devez utiliser deux attributs spéciaux :

1. `#[TypesenseIndexable]` : pour configurer la collection
2. `#[TypesenseField]` : pour configurer les champs à indexer

### 1. Configuration de la Collection avec TypesenseIndexable

```php
use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;

#[TypesenseIndexable(
    collection: 'products',
    normalizerMethod: 'getSearchData',
    defaultSortingField: 'created_at',
    enableNestedFields: true,
    nestedFields: ['categories', 'tags']
)]
class Product
{
    // ...
}
```

Les options disponibles pour `#[TypesenseIndexable]` sont :
- `collection`: Le nom de la collection Typesense (obligatoire)
- `normalizerMethod`: Méthode personnalisée pour normaliser les données (optionnel)
- `defaultSortingField`: Champ par défaut pour le tri (optionnel)
- `enableNestedFields`: Activer les champs imbriqués (optionnel)
- `nestedFields`: Liste des champs imbriqués à indexer (optionnel)

### 2. Configuration des Champs avec TypesenseField

```php
use Micka17\TypesenseBundle\Attribute\TypesenseField;

#[TypesenseIndexable(collection: 'products')]
class Product
{
    #[TypesenseField(
        name: 'name',
        type: 'string',
        facet: true,
        sort: true
    )]
    private string $name;

    #[TypesenseField(
        name: 'price',
        type: 'float',
        optional: true
    )]
    private float $price;

    #[TypesenseField(
        name: 'categories',
        type: 'string[]',
        facet: true
    )]
    private array $categories;
}
```

Les options disponibles pour `#[TypesenseField]` sont :
- `name`: Nom du champ dans Typesense (optionnel, utilise le nom de la propriété par défaut)
- `type`: Type du champ (auto, string, int32, float, bool, string[]) (optionnel, auto-détecté)
- `getter`: Méthode getter personnalisée (optionnel)
- `facet`: Champ utilisable pour les facettes/filtres (optionnel)
- `optional`: Champ optionnel (optionnel)
- `sort`: Champ utilisable pour le tri (optionnel)

### 3. Gestion des Synonymes

Les synonymes sont essentiels pour améliorer la pertinence des résultats de recherche en associant des termes similaires. Vous pouvez gérer les synonymes de deux manières : globalement dans votre configuration ou spécifiquement sur vos entités.

#### Synonymes Globaux

Définissez les synonymes globaux dans votre fichier `config/packages/typesense.yaml` :

```yaml
typesense:
    # ...
    synonyms:
        - { id: 'size-synonyms', synonyms: ['large', 'big', 'huge'] }
        - { id: 'color-synonyms', root: 'primary_color', synonyms: ['red', 'blue', 'green'] }
```

#### Synonymes par Entité

Utilisez l'attribut `#[TypesenseSynonym]` sur vos classes d'entités pour définir des synonymes spécifiques à une collection. Cela permet une gestion fine des synonymes, adaptée au contexte de chaque type d'entité.

```php
use Micka17\TypesenseBundle\Attribute\TypesenseSynonym;

#[TypesenseIndexable(collection: 'products')]
#[TypesenseSynonym(id: 'product-synonyms', synonyms: ['item', 'good', 'merchandise'])]
class Product
{
    // ...
}
```

L'attribut `#[TypesenseSynonym]` prend les options suivantes :
- `id`: Un identifiant unique pour cet ensemble de synonymes (obligatoire).
- `synonyms`: Un tableau de chaînes de caractères représentant les mots ou expressions synonymes (obligatoire).
- `root`: (Optionnel) Le terme principal si les synonymes sont unidirectionnels (par exemple, "pomme" -> ["fruit", "golden"]). Si non spécifié, les synonymes sont bidirectionnels.

#### Appliquer les Synonymes

Une fois vos synonymes configurés (que ce soit globalement ou par entité), vous devez les appliquer à votre instance Typesense. Le bundle fournit une commande dédiée pour cela :

```bash
php bin/console micka17:typesense:synonyms:apply
```

Cette commande lit toutes les configurations de synonymes (depuis `typesense.yaml` et les attributs `#[TypesenseSynonym]` sur vos entités indexables) et les envoie à Typesense. Elle gère la création, la mise à jour et la suppression des ensembles de synonymes pour s'assurer que votre instance Typesense est toujours synchronisée avec votre configuration.

Vous pouvez également gérer les synonymes manuellement avec la commande `micka17:typesense:synonyms` pour des opérations plus granulaires (création, suppression, listage) directement via la ligne de commande.

### Auto-Update

Le bundle supporte l'auto-update des entités indexées. Cette fonctionnalité est activée par défaut dans la configuration :

```yaml
typesense:
    auto_update: true  # par défaut
```

Lorsque cette option est activée, les entités indexées seront automatiquement mises à jour dans Typesense lors des événements Doctrine suivants :
- `postPersist`: lors de la création d'une nouvelle entité
- `postUpdate`: lors de la mise à jour d'une entité
- `preRemove`: lors de la suppression d'une entité

### Commandes d'Administration

Le bundle fournit plusieurs commandes pour gérer l'indexation :

```bash
# Créer une collection
php bin/console micka17:typesense:manage create "App\Entity\Product"

# Recréer une collection (supprime et recrée)
php bin/console micka17:typesense:manage recreate "App\Entity\Product"

# Re-indexer toutes les entités d'une collection
php bin/console micka17:typesense:manage reindex "App\Entity\Product"

# Supprimer une collection
php bin/console micka17:typesense:manage delete "App\Entity\Product"

# Gérer les synonymes manuellement
php bin/console micka17:typesense:synonyms <action> <synonym-id> <synonyms> --collection=<collection>

# Appliquer les synonymes depuis la configuration et les attributs
php bin/console micka17:typesense:synonyms:apply
```

## License

Ce bundle est distribué sous la licence MIT.