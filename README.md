# TypesenseBundle
Ce bundle facilite l'intégration de Typesense dans vos applications Symfony. Il permet d'indexer automatiquement vos entités Doctrine dans Typesense et de gérer les opérations de recherche.

## Installation

```bash
composer require micka-17/typesense-bundle
```

## Configuration

```yaml
typesense:
    api_key: '%env(TYPENSE_API_KEY)%'
    nodes:
        - host: '%env(TYPENSE_HOST)%'
          port: '%env(TYPENSE_PORT)%'
          protocol: '%env(TYPENSE_PROTOCOL)%'
    
    indexable_entities:
        - App\Entity\Product
        - App\Entity\Category
    
    auto_update: true
```

## Utilisation

### Indexation des Entités

```php
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
}
```

### Recherche

```php
use Typesense\Service\TypesenseManager;

class YourService
{
    public function __construct(private TypesenseManager $typesenseManager) {}

    public function search(string $query)
    {
        return $this->typesenseManager->search($query);
    }
}
```

## Commandes

```bash
# Créer une collection
php bin/console micka17:typesense:manage create "App\Entity\Product"

# Recréer une collection
php bin/console micka17:typesense:manage recreate "App\Entity\Product"

# Re-indexer une collection
php bin/console micka17:typesense:manage reindex "App\Entity\Product"

# Supprimer une collection
php bin/console micka17:typesense:manage delete "App\Entity\Product"
```

## Documentation Complète

Pour plus de détails sur la configuration et l'utilisation, consultez la [documentation complète](docs/index.md).

## Contribuer

Les contributions sont les bienvenues ! N'hésitez pas à créer des issues ou des pull requests.

## Support

Pour toute question ou problème, ouvrez une issue sur le repository GitHub du projet.

## License

Ce bundle est distribué sous la licence MIT.
