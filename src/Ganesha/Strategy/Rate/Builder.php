<?php

namespace Ackintosh\Ganesha\Strategy\Rate;

use Ackintosh\Ganesha\Configuration;
use Ackintosh\Ganesha\Storage\AdapterInterface;
use Ackintosh\Ganesha\Storage\Adapter\SlidingTimeWindowInterface;
use Ackintosh\Ganesha\Storage\Adapter\TumblingTimeWindowInterface;
use Ackintosh\Ganesha\Storage\StorageKeysInterface;
use Ackintosh\Ganesha\Traits\BuildGanesha;

class Builder
{
    use BuildGanesha;

    private array $params = [];

    /**
     * @var array
     */
    private static $requirements = [
        Configuration::ADAPTER,
        Configuration::FAILURE_RATE_THRESHOLD,
        Configuration::INTERVAL_TO_HALF_OPEN,
        Configuration::MINIMUM_REQUESTS,
        Configuration::TIME_WINDOW,
    ];

    /** @var string */
    private static $strategyClass = 'Ackintosh\Ganesha\Strategy\Rate';

    /** @var string */
    private static $adapterRequirement = 'supportRateStrategy';

    /**
     * @psalm-param (AdapterInterface&SlidingTimeWindowInterface)|(AdapterInterface&TumblingTimeWindowInterface) $adapter
     */
    public function adapter(AdapterInterface $adapter): self
    {
        $this->params[Configuration::ADAPTER] = $adapter;
        return $this;
    }

    /**
     * @psalm-param int<1, 100> $failureRateThreshold
     */
    public function failureRateThreshold(int $failureRateThreshold): self
    {
        $this->params[Configuration::FAILURE_RATE_THRESHOLD] = $failureRateThreshold;
        return $this;
    }

    /**
     * @psalm-param int<1, max> $intervalToHalfOpen
     */
    public function intervalToHalfOpen(int $intervalToHalfOpen): self
    {
        $this->params[Configuration::INTERVAL_TO_HALF_OPEN] = $intervalToHalfOpen;
        return $this;
    }

    public function storageKeys(StorageKeysInterface $storageKeys): self
    {
        $this->params[Configuration::STORAGE_KEYS] = $storageKeys;
        return $this;
    }

    /**
     * @psalm-param int<1, max> $minimumRequests
     */
    public function minimumRequests(int $minimumRequests): self
    {
        $this->params[Configuration::MINIMUM_REQUESTS] = $minimumRequests;
        return $this;
    }

    /**
     * @psalm-param int<1, max> $timeWindow
     */
    public function timeWindow(int $timeWindow): self
    {
        $this->params[Configuration::TIME_WINDOW] = $timeWindow;
        return $this;
    }
}
