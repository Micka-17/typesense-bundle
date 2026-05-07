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
use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TypesenseManager $manager,
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
    ) {}

    #[Route('/', name: 'micka17_typesense_admin_dashboard')]
    public function dashboard(): Response
    {
        $health = [];
        $collections = [];
        $counts = [];

        try {
            $health = $this->client->health();
            $collections = $this->client->getClient()->collections->retrieve();
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Connexion Typesense échouée : ' . $e->getMessage());
        }

        $fetch = static function (callable $fn): int {
            try {
                $data = $fn();
                // Most list endpoints return ['results' => [...]] or a flat array of items
                if (isset($data['presets'])) return count($data['presets']);
                if (isset($data['synonym_sets'])) return count($data['synonym_sets']);
                if (isset($data['curation_sets'])) return count($data['curation_sets']);
                if (isset($data['dictionaries'])) return count($data['dictionaries']);
                if (isset($data['rules'])) return count($data['rules']);
                if (isset($data['models'])) return count($data['models']);
                if (isset($data['aliases'])) return count($data['aliases']);
                if (isset($data['keys'])) return count($data['keys']);
                if (is_array($data)) return count($data);
                return 0;
            } catch (\Throwable) {
                return 0;
            }
        };

        $counts = [
            'collections'    => count($collections),
            'presets'        => $fetch(fn() => $this->presetManager->listPresets()),
            'synonym_sets'   => $fetch(fn() => $this->synonymSetManager->listSynonymSets()),
            'curation_sets'  => $fetch(fn() => $this->curationSetManager->listCurationSets()),
            'stemming'       => $fetch(fn() => $this->stemmingManager->listDictionaries()),
            'analytics'      => $fetch(fn() => $this->analyticsManager->listRules()),
            'nl_search'      => $fetch(fn() => $this->nlSearchManager->listModels()),
            'conversations'  => $fetch(fn() => $this->conversationManager->listModels()),
            'aliases'        => $fetch(fn() => $this->aliasManager->listAliases()),
            'keys'           => $fetch(fn() => $this->keysManager->listKeys()),
        ];

        return $this->render('@Micka17Typesense/admin/dashboard/index.html.twig', [
            'health'      => $health,
            'collections' => $collections,
            'counts'      => $counts,
        ]);
    }

    #[Route('/collection/{collectionName}/delete', name: 'micka17_typesense_admin_delete_collection', methods: ['POST'])]
    public function deleteCollection(string $collectionName): Response
    {
        try {
            $this->manager->deleteCollection($collectionName);
            $this->addFlash('success', sprintf('Collection "%s" supprimée.', $collectionName));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('micka17_typesense_admin_resource_list', ['resource' => 'collections']);
    }

    #[Route('/collection/{entityClass}/reindex', name: 'micka17_typesense_admin_reindex_collection', methods: ['POST'])]
    public function reindexCollection(string $entityClass): Response
    {
        try {
            $count = $this->manager->reindexEntityCollection($entityClass);
            $this->addFlash('success', sprintf('%d document(s) réindexé(s) pour "%s".', $count, $entityClass));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('micka17_typesense_admin_resource_list', ['resource' => 'collections']);
    }

    #[Route('/collection/{entityClass}/recreate', name: 'micka17_typesense_admin_recreate_collection', methods: ['POST'])]
    public function recreateCollection(string $entityClass): Response
    {
        try {
            $this->manager->recreateCollectionForEntity($entityClass);
            $this->addFlash('success', sprintf('Collection pour "%s" recréée.', $entityClass));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('micka17_typesense_admin_resource_list', ['resource' => 'collections']);
    }
}
