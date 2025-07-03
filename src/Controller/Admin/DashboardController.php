<?php

namespace Micka17\TypesenseBundle\Controller\Admin;

use Micka17\TypesenseBundle\Service\TypesenseManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/dashboard')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TypesenseManager $typesenseManager,
        private readonly ParameterBagInterface $parameterBag,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    #[Route('/', name: 'micka17_typesense_admin_dashboard')]
    public function dashboard(): Response
    {
        $collections = [];
        $metrics = [];
        $stats = [];
        $nodesDetails = [];
        $entityCollectionMap = [];
        $clusterHealth = [
            'status' => 'red',
            'message' => 'Impossible de récupérer l\'état du cluster.',
            'documents' => 0
        ];

        try {
            $nodesConfig = $this->parameterBag->get('typesense.cluster.nodes');
            if (empty($nodesConfig)) {
                throw new \RuntimeException('La configuration des nœuds Typesense (typesense.cluster.nodes) est vide.');
            }
            $apiKey = $this->parameterBag->get('typesense.api_key');
            $totalNodeCount = count($nodesConfig);

            $activeNodeCount = 0;
            $leaderCount = 0;

            foreach ($nodesConfig as $nodeConfig) {
                $nodeUrl = sprintf('%s://%s:%s', $nodeConfig['protocol'], $nodeConfig['host'], $nodeConfig['port']);
                $nodeInfo = [
                    'name' => $nodeConfig['host'] . ':' . $nodeConfig['port'],
                    'is_healthy' => false,
                    'state' => 'Unknown',
                    'version' => 'N/A',
                    'error' => null
                ];

                try {
                    $healthResponse = $this->httpClient->request('GET', $nodeUrl . '/health', [
                        'headers' => ['X-TYPESENSE-API-KEY' => $apiKey]
                    ]);

                    if ($healthResponse->getStatusCode() === 200) {
                        $nodeInfo['is_healthy'] = $healthResponse->toArray()['ok'] ?? false;
                    }

                    if ($nodeInfo['is_healthy']) {
                        $activeNodeCount++;
                        $debugResponse = $this->httpClient->request('GET', $nodeUrl . '/debug', [
                            'headers' => ['X-TYPESENSE-API-KEY' => $apiKey]
                        ]);
                        $debugData = $debugResponse->toArray();
                        $nodeInfo['version'] = $debugData['version'] ?? 'N/A';
                        if (isset($debugData['state'])) {
                            if ($debugData['state'] === 1) {
                                $leaderCount++;
                                $nodeInfo['state'] = 'Leader';
                            } elseif ($debugData['state'] === 4) {
                                $nodeInfo['state'] = 'Follower';
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $nodeInfo['error'] = $e->getMessage();
                }
                $nodesDetails[] = $nodeInfo;
            }

            $quorum = floor($totalNodeCount / 2) + 1;
            if ($activeNodeCount < $quorum) {
                $clusterHealth['status'] = 'red';
                $clusterHealth['message'] = "Quorum perdu ! Seuls $activeNodeCount/$totalNodeCount nœuds sont actifs.";
            } elseif ($leaderCount === 0) {
                $clusterHealth['status'] = 'yellow';
                $clusterHealth['message'] = "Cluster dégradé : $activeNodeCount/$totalNodeCount nœuds actifs mais aucun Leader. Les écritures vont échouer.";
            } elseif ($leaderCount > 1) {
                $clusterHealth['status'] = 'red';
                $clusterHealth['message'] = "Erreur critique (Split Brain) : $leaderCount nœuds se considèrent comme Leader.";
            } else {
                $clusterHealth['status'] = 'green';
                $clusterHealth['message'] = "Cluster opérationnel : $activeNodeCount/$totalNodeCount nœuds actifs et 1 Leader.";
            }

            $client = $this->typesenseManager->getClient()->getClient();
            $collections = $client->collections->retrieve();
            $metrics = $client->metrics->retrieve();
            $stats = [];

            try {
                $firstNodeConfig = $this->parameterBag->get('typesense.cluster.nodes')[0] ?? null;
                if ($firstNodeConfig) {
                    $nodeUrl = sprintf(
                        '%s://%s:%s', 
                        $firstNodeConfig['protocol'], 
                        $firstNodeConfig['host'], 
                        $firstNodeConfig['port']
                    );
                    
                    $statsResponse = $this->httpClient->request('GET', $nodeUrl . '/stats.json', [
                        'headers' => ['X-TYPESENSE-API-KEY' => $apiKey]
                    ]);

                    if ($statsResponse->getStatusCode() === 200) {
                        $stats = $statsResponse->toArray();
                    }
                }
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Impossible de récupérer les stats via /stats.json : ' . $e->getMessage());
            }

            $clusterHealth['documents'] = array_sum(array_column($collections, 'num_documents'));
            $entities = $this->parameterBag->get('typesense.indexable_entities', []);
            $entityCollectionMap = [];
            $missingCollections = [];
            $existingCollectionNames = array_column($collections, 'name');
            
            foreach ($entities as $entityClass) {
                $schema = $this->typesenseManager->getSchemaGenerator()->generate($entityClass);
                $collectionName = $schema['name'];
                $entityCollectionMap[$collectionName] = $entityClass;
                
                if (!in_array($collectionName, $existingCollectionNames)) {
                    $missingCollections[] = [
                        'entityClass' => $entityClass,
                        'collectionName' => $collectionName
                    ];
                }
            }

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Une erreur critique est survenue lors de la communication avec Typesense : ' . $e->getMessage());
        }

        return $this->render('@Typesense/admin/dashboard/index.html.twig', [
            'collections' => $collections,
            'metrics' => $metrics,
            'stats' => $stats,
            'nodesDetails' => $nodesDetails,
            'clusterHealth' => $clusterHealth,
            'entityCollectionMap' => $entityCollectionMap,
            'missingCollections' => $missingCollections,
        ]);
    }

    #[Route('/collection/{collectionName}/delete', name: 'micka17_typesense_admin_delete_collection', methods: ['POST'])]
    public function deleteCollection(Request $request, string $collectionName): Response
    {
        if ($this->isCsrfTokenValid('delete' . $collectionName, $request->request->get('_token'))) {
            try {
                $this->typesenseManager->deleteCollection($collectionName);
                $this->addFlash('success', sprintf('Collection "%s" supprimée avec succès.', $collectionName));
            } catch (\Exception $e) {
                $this->addFlash('danger', sprintf('Erreur lors de la suppression de la collection "%s": %s', $collectionName, $e->getMessage()));
            }
        }

        return $this->redirectToRoute('micka17_typesense_admin_dashboard');
    }

    #[Route('/collection/{entityClass}/reindex', name: 'micka17_typesense_admin_reindex_collection', methods: ['POST'])]
    public function reindexCollection(Request $request, string $entityClass): Response
    {        
        try {
            $this->typesenseManager->reindexEntityCollection($entityClass);
            $this->addFlash('success', sprintf('La collection pour l\'entité "%s" a été réindexée avec succès.', $entityClass));
        } catch (\Exception $e) {
            $this->addFlash('danger', sprintf('Erreur lors de la réindexation de la collection pour l\'entité "%s": %s', $entityClass, $e->getMessage()));
        }

        return $this->redirectToRoute('micka17_typesense_admin_dashboard');
    }

    #[Route('/collection/{entityClass}/recreate', name: 'micka17_typesense_admin_recreate_collection', methods: ['POST'])]
    public function recreateCollection(Request $request, string $entityClass): Response
    {
        if ($this->isCsrfTokenValid('recreate' . $entityClass, $request->request->get('_token'))) {
            try {
                $this->typesenseManager->recreateCollectionForEntity($entityClass);
                $this->addFlash('success', sprintf('La collection pour l\'entité "%s" a été recréée avec succès.', $entityClass));
            } catch (\Exception $e) {
                $this->addFlash('danger', sprintf('Erreur lors de la recréation de la collection pour l\'entité "%s": %s', $entityClass, $e->getMessage()));
            }
        }

        return $this->redirectToRoute('micka17_typesense_admin_dashboard');
    }
}