<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Attribute\TypesenseIndexable;
use Micka17\TypesenseBundle\Service\SchemaGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Micka17\TypesenseBundle\Service\TypesenseClient;
use Typesense\Exceptions\ObjectAlreadyExists;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'typesense:create-collection',
    description: 'Crée une collection Typesense à partir d\'une classe Entité possédant l\'attribut TypesenseIndexable.',
)]
class TypesenseCreateCollectionCommand extends Command
{
    private SchemaGenerator $schemaGenerator;
    private TypesenseClient $typesenseClient;
    private string $projectDir;

    public function __construct(SchemaGenerator $schemaGenerator, TypesenseClient $typesenseClient, #[Autowire('%kernel.project_dir%')] ParameterBagInterface $projectDir)
    {
        $this->schemaGenerator = $schemaGenerator;
        $this->typesenseClient = $typesenseClient;
        $this->projectDir = $projectDir->get('kernel.project_dir');
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::OPTIONAL, 'La classe Entité à traiter (ex: App\Entity\Product). Si non spécifié, scanne src/Entity.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $entityClass = $input->getArgument('entity');

        if ($entityClass) {
            if (!class_exists($entityClass)) {
                $io->error("La classe '$entityClass' n'existe pas.");
                return Command::FAILURE;
            }
            $this->processEntity($entityClass, $io);
        } else {
            $io->info('Aucune entité spécifiée. Scan du répertoire src/Entity...');
            $finder = new Finder();
            $finder->in($this->projectDir . '/src/Entity')->files()->name('*.php');

            if (!$finder->hasResults()) {
                 $io->warning('Aucun fichier PHP trouvé dans src/Entity.');
                 return Command::SUCCESS;
            }

            foreach ($finder as $file) {
                $class = 'App\\Entity\\' . $file->getBasename('.php');
                if (class_exists($class)) {
                    $this->processEntity($class, $io);
                }
            }
        }

        $io->success('Opération terminée.');

        return Command::SUCCESS;
    }

    private function processEntity(string $className, SymfonyStyle $io): void
    {
        try {
            $reflectionClass = new \ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(TypesenseIndexable::class);

            if (count($attributes) === 0) {
                $io->writeln("-> Classe '$className': Pas d'attribut #[TypesenseIndexable], ignorée.");
                return;
            }

            $io->writeln("-> Classe '$className': Attribut #[TypesenseIndexable] trouvé. Génération du schéma...");
            
            $schema = $this->schemaGenerator->generate($className);
            $collectionName = $schema['name'];

            $io->info("Création de la collection '$collectionName' sur Typesense...");

            try {
                $this->typesenseClient->client->collections->create($schema);
                $io->success("La collection '$collectionName' a été créée avec succès !");
            } catch (ObjectAlreadyExists $e) {
                $io->warning("La collection '$collectionName' existe déjà. Aucune action n'a été effectuée.");
            } catch (\Exception $e) {
                $io->error("Une erreur est survenue lors de la création de la collection '$collectionName': " . $e->getMessage());
            }

        } catch (\ReflectionException $e) {
            $io->error("Erreur de réflexio pour la classe '$className': " . $e->getMessage());
        }
    }
}