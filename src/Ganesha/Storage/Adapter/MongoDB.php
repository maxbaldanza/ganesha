<?php

namespace Ackintosh\Ganesha\Storage\Adapter;

use Ackintosh\Ganesha;
use Ackintosh\Ganesha\Configuration;
use Ackintosh\Ganesha\Exception\StorageException;
use Ackintosh\Ganesha\Storage\AdapterInterface;
use MongoDB\Driver\Cursor;

class MongoDB implements AdapterInterface, TumblingTimeWindowInterface, SlidingTimeWindowInterface
{
    /**
     * @var \MongoDB\Driver\Manager
     */
    private $manager;

    /**
     * @var string
     */
    private $dbName;

    /**
     * @var string
     */
    private $collectionName;

    public function __construct(\MongoDB\Driver\Manager $manager, string $dbName, string $collectionName)
    {
        $this->manager = $manager;
        $this->dbName = $dbName;
        $this->collectionName = $collectionName;
    }

    public function supportCountStrategy(): bool
    {
        return true;
    }

    public function supportRateStrategy(): bool
    {
        return true;
    }

    /**
     * @codeCoverageIgnore
     */
    public function setContext(Ganesha\Context $context): void
    {
        // This adapter doesn't use the context.
    }

    /**
     * @inheritdoc
     */
    public function setConfiguration(Configuration $configuration): void
    {
        // nop
    }

    /**
     * @throws StorageException
     */
    public function load(string $service): int
    {
        $cursor = $this->read(['service' => $service]);
        $result = $cursor->toArray();
        if ($result === null || empty($result)) {
            $this->update(['service' => $service], ['$set' => ['count' => 0]]);
            return 0;
        }
        if (!isset($result[0]['count'])) {
            throw new StorageException('failed to load service : file "count" not found.');
        }

        return $result[0]['count'];
    }

    /**
     * @throws StorageException
     */
    public function save(string $service, int $count): void
    {
        $this->update(['service' => $service], ['$set' => ['count' => $count]]);
    }

    /**
     * @throws StorageException
     */
    public function increment(string $service): void
    {
        $this->update(['service' => $service], ['$inc' => ['count' => 1]], ['safe' => true]);
    }

    /**
     * @throws StorageException
     */
    public function decrement(string $service): void
    {
        $this->update(['service' => $service], ['$inc' => ['count' => -1]], ['safe' => true]);
    }

    /**
     * @throws StorageException
     */
    public function saveLastFailureTime(string $service, int $lastFailureTime): void
    {
        $this->update(['service' => $service], ['$set' => ['lastFailureTime' => $lastFailureTime]]);
    }

    /**
     * @throws StorageException
     */
    public function loadLastFailureTime(string $service): int
    {
        $cursor = $this->read(['service' => $service]);
        $result = $cursor->toArray();
        if ($result === null || empty($result)) {
            throw new StorageException('failed to last failure time : entry not found.');
        }
        if (!isset($result[0]['lastFailureTime'])) {
            throw new StorageException('failed to last failure time : field "lastFailureTime" not found.');
        }

        return $result[0]['lastFailureTime'];
    }

    /**
     * @throws StorageException
     */
    public function saveStatus(string $service, int $status): void
    {
        $this->update(['service' => $service], ['$set' => ['status' => $status]]);
    }

    /**
     * @throws StorageException
     */
    public function loadStatus(string $service): int
    {
        $cursor = $this->read(['service' => $service]);
        $result = $cursor->toArray();

        if ($result === null || empty($result) || !isset($result[0]['status'])) {
            $this->saveStatus($service, Ganesha::STATUS_CALMED_DOWN);
            return Ganesha::STATUS_CALMED_DOWN;
        }

        return $result[0]['status'];
    }

    public function reset(): void
    {
        $this->delete([], []);
    }

    /**
     * @return string "db.collectionName"
     */
    private function getNamespace(): string
    {
        return $this->dbName . '.' . $this->collectionName;
    }

    private function read(array $filter, array $queryOptions = []): Cursor
    {
        try {
            $query = new \MongoDB\Driver\Query($filter, $queryOptions);
            $cursor = $this->manager->executeQuery($this->getNamespace(), $query);
            $cursor->setTypeMap(['root' => 'array', 'document' => 'array', 'array' => 'array']);
            return $cursor;
        } catch (\MongoDB\Driver\Exception\Exception $ex) {
            throw new StorageException('adapter error : ' . $ex->getMessage());
        }
    }

    private function delete(array $filter, array $deleteOptions = []): void
    {
        $this->bulkWrite($filter, $options = ['deleteOptions' => $deleteOptions], 'delete');
    }

    private function update(array $filter, array $newObj, array $updateOptions = ['multi' => false, 'upsert' => true]): void
    {
        $this->bulkWrite($filter, $options = ['newObj' => $newObj, 'updateOptions' => $updateOptions], 'update');
    }

    private function bulkWrite(array $filter, array $options, string $command): void
    {
        try {
            $bulk = new \MongoDB\Driver\BulkWrite();
            switch ($command) {
                case 'update':
                    if (isset($options['newObj']['$set'])) {
                        $options['newObj']['$set']['date'] = new \MongoDB\BSON\UTCDateTime();
                    }
                    $bulk->update($filter, $options['newObj'], $options['updateOptions']);
                    break;
                case 'delete':
                    $bulk->delete($filter, $options['deleteOptions']);
                    break;
            }
            $writeConcern = new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY, 100);
            $result = $this->manager->executeBulkWrite($this->getNamespace(), $bulk, $writeConcern);
            if (!empty($result->getWriteErrors())) {
                $errorMessage = '';
                foreach ($result->getWriteErrors() as $writeError) {
                    $errorMessage .= 'Operation#' . $writeError->getIndex() . ': ' . $writeError->getMessage() . ' (' . $writeError->getCode() . ')' . "\n";
                }
                throw new StorageException('failed '.$command.' the value : ' . $errorMessage);
            }
        } catch (\MongoDB\Driver\Exception\Exception $ex) {
            throw new StorageException('adapter error : ' . $ex->getMessage());
        }
    }
}
