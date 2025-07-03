<?php

namespace Micka17\TypesenseBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Typesense\Exceptions\TypesenseClientError;

class TypesenseErrorTracker
{
    private const DEFAULT_ERROR_FIELDS = ['host', 'port', 'protocol', 'path', 'error_message', 'error_code', 'timestamp'];
    private array $filteredNodeErrorFields;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly KernelInterface $kernel,
        private readonly bool $enabled,
        private readonly string $logLevel,
        private readonly bool $trackNodeErrors,
        private readonly array $nodeErrorFields
    ) {
        $this->filteredNodeErrorFields = array_intersect($this->nodeErrorFields, self::DEFAULT_ERROR_FIELDS);
    }
    
    public function getFormattedNodeDetails(\Throwable $exception): ?string
    {
        $nodeInfo = $this->extractNodeInfo($exception);
        
        if ($nodeInfo === null) {
            return null;
        }

        $details = [];
        if (!empty($nodeInfo['host'])) {
            $details[] = $nodeInfo['host'];
        }
        if (!empty($nodeInfo['port'])) {
            $details[] = $nodeInfo['port'];
        }

        if (empty($details)) {
            return null;
        }

        return "Noeud problématique : " . implode(':', $details);
    }

    public function trackError(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $logContext = [
            'type' => 'typesense_error',
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->kernel->getEnvironment(),
        ];
        
        $logContext = array_merge($logContext, $context);

        if ($exception) {
            $logContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
            
            $nodeContext = $this->extractNodeInfo($exception);
            if ($nodeContext !== null) {
                $logContext['node'] = $nodeContext;
            }
        }

        $this->logger->log($this->logLevel, $message, $logContext);
    }

    private function extractNodeInfo(\Throwable $exception): ?array
    {
        if ($this->trackNodeErrors && $exception instanceof TypesenseClientError && method_exists($exception, 'getNode')) {
            $node = $exception->getNode();
            $nodeContext = [];
            foreach ($this->filteredNodeErrorFields as $field) {
                if (isset($node[$field])) {
                    $nodeContext[$field] = $node[$field];
                }
            }
            return empty($nodeContext) ? null : $nodeContext;
        }
        
        return null;
    }
}