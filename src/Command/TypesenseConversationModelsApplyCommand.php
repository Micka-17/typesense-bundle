<?php

namespace Micka17\TypesenseBundle\Command;

use Micka17\TypesenseBundle\Service\ConversationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'micka17:typesense:conversation-models:apply', description: 'Apply configured Typesense conversation models.')]
class TypesenseConversationModelsApplyCommand extends Command
{
    /**
     * @param array<string, array<string, mixed>> $models
     */
    public function __construct(private readonly ConversationManager $manager, private readonly array $models)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->models === []) {
            $io->success('No conversation models configured.');
            return Command::SUCCESS;
        }

        try {
            $this->manager->applyConfiguredModels($this->models);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());
            return Command::FAILURE;
        }

        $io->success('Conversation models applied.');

        return Command::SUCCESS;
    }
}
