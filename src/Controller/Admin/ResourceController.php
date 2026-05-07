<?php

declare(strict_types=1);

namespace Micka17\TypesenseBundle\Controller\Admin;

use Micka17\TypesenseBundle\Service\AliasManager;
use Micka17\TypesenseBundle\Service\KeysManager;
use Micka17\TypesenseBundle\Service\AnalyticsManager;
use Micka17\TypesenseBundle\Service\ConversationManager;
use Micka17\TypesenseBundle\Service\CurationSetManager;
use Micka17\TypesenseBundle\Service\NaturalLanguageSearchManager;
use Micka17\TypesenseBundle\Service\PresetManager;
use Micka17\TypesenseBundle\Service\StemmingManager;
use Micka17\TypesenseBundle\Service\SynonymSetManager;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResourceController extends AbstractController
{
    /** @var array<string, array{label: string, icon: string, idKey: string, list: callable, delete: callable, columns: array<string, string>}> */
    private array $registry;

    public function __construct(
        private readonly TypesenseClient $client,
        private readonly PresetManager $presetManager,
        private readonly SynonymSetManager $synonymSetManager,
        private readonly CurationSetManager $curationSetManager,
        private readonly StemmingManager $stemmingManager,
        private readonly AnalyticsManager $analyticsManager,
        private readonly NaturalLanguageSearchManager $nlSearchManager,
        private readonly ConversationManager $conversationManager,
        private readonly AliasManager $aliasManager,
        private readonly KeysManager $keysManager,
    ) {
        $this->registry = [
            'collections' => [
                'label'   => 'Collections',
                'icon'    => 'bi-collection',
                'idKey'   => 'name',
                'columns' => ['Nom' => 'name', 'Documents' => 'num_documents', 'Champs' => '_fields_count', 'Créée le' => '_created_at'],
                'list'    => fn() => $this->client->getClient()->collections->retrieve(),
                'delete'  => fn(string $id) => $this->client->deleteCollection($id),
            ],
            'presets' => [
                'label'   => 'Presets',
                'icon'    => 'bi-sliders',
                'idKey'   => 'name',
                'columns' => ['Nom' => 'name', 'Paramètres' => '_params_count'],
                'list'    => fn() => $this->presetManager->listPresets()['presets'] ?? [],
                'delete'  => fn(string $id) => $this->presetManager->deletePreset($id),
            ],
            'synonym-sets' => [
                'label'   => 'Synonym Sets',
                'icon'    => 'bi-arrow-left-right',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id', 'Synonymes' => '_synonyms_count'],
                'list'    => fn() => $this->synonymSetManager->listSynonymSets()['synonym_sets'] ?? [],
                'delete'  => fn(string $id) => $this->synonymSetManager->deleteSynonymSet($id),
            ],
            'curation-sets' => [
                'label'   => 'Curation Sets',
                'icon'    => 'bi-funnel',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id', 'Type' => 'type'],
                'list'    => fn() => $this->curationSetManager->listCurationSets()['curation_sets'] ?? [],
                'delete'  => fn(string $id) => $this->curationSetManager->deleteCurationSet($id),
            ],
            'stemming' => [
                'label'   => 'Stemming Dictionaries',
                'icon'    => 'bi-tree',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id'],
                'list'    => fn() => $this->stemmingManager->listDictionaries()['dictionaries'] ?? [],
                'delete'  => fn(string $id) => $this->stemmingManager->deleteDictionary($id),
            ],
            'analytics' => [
                'label'   => 'Analytics Rules',
                'icon'    => 'bi-bar-chart-line',
                'idKey'   => 'name',
                'columns' => ['Nom' => 'name', 'Type' => 'type'],
                'list'    => fn() => $this->analyticsManager->listRules()['rules'] ?? [],
                'delete'  => fn(string $id) => $this->analyticsManager->deleteRule($id),
            ],
            'nl-search-models' => [
                'label'   => 'NL Search Models',
                'icon'    => 'bi-translate',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id', 'Modèle' => 'model_name'],
                'list'    => fn() => $this->nlSearchManager->listModels()['models'] ?? [],
                'delete'  => fn(string $id) => $this->nlSearchManager->deleteModel($id),
            ],
            'conversations' => [
                'label'   => 'Conversation Models',
                'icon'    => 'bi-chat-dots',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id', 'Modèle' => 'model_name'],
                'list'    => fn() => $this->conversationManager->listModels()['models'] ?? [],
                'delete'  => fn(string $id) => $this->conversationManager->deleteModel($id),
            ],
            'aliases' => [
                'label'   => 'Aliases',
                'icon'    => 'bi-link-45deg',
                'idKey'   => 'name',
                'columns' => ['Nom' => 'name', 'Collection' => 'collection_name'],
                'list'    => fn() => $this->aliasManager->listAliases()['aliases'] ?? [],
                'delete'  => fn(string $id) => $this->aliasManager->deleteAlias($id),
            ],
            'keys' => [
                'label'   => 'API Keys',
                'icon'    => 'bi-key',
                'idKey'   => 'id',
                'columns' => ['ID' => 'id', 'Description' => 'description', 'Actions' => '_actions', 'Collections' => '_collections'],
                'list'    => fn() => $this->keysManager->listKeys()['keys'] ?? [],
                'delete'  => fn(string $id) => $this->keysManager->deleteKey((int) $id),
            ],
        ];
    }

    #[Route('/{resource}', name: 'micka17_typesense_admin_resource_list', requirements: ['resource' => 'collections|presets|synonym-sets|curation-sets|stemming|analytics|nl-search-models|conversations|aliases|keys'])]
    public function list(string $resource): Response
    {
        $config = $this->registry[$resource];
        $items = [];

        try {
            $raw = ($config['list'])();
            $items = is_array($raw) ? $raw : [];
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur lors du chargement : ' . $e->getMessage());
        }

        return $this->render('@Micka17Typesense/admin/resource/list.html.twig', [
            'resource' => $resource,
            'label'    => $config['label'],
            'icon'     => $config['icon'],
            'idKey'    => $config['idKey'],
            'columns'  => $config['columns'],
            'items'    => $items,
        ]);
    }

    #[Route('/{resource}/{id}/delete', name: 'micka17_typesense_admin_resource_delete', methods: ['POST'], requirements: ['resource' => 'collections|presets|synonym-sets|curation-sets|stemming|analytics|nl-search-models|conversations|aliases|keys'])]
    public function delete(Request $request, string $resource, string $id): Response
    {
        if (!$this->isCsrfTokenValid('delete_' . $resource . '_' . $id, $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('micka17_typesense_admin_resource_list', ['resource' => $resource]);
        }

        $config = $this->registry[$resource];

        try {
            ($config['delete'])($id);
            $this->addFlash('success', sprintf('"%s" supprimé avec succès.', $id));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('micka17_typesense_admin_resource_list', ['resource' => $resource]);
    }
}
