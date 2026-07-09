<?php

namespace Core;

abstract class Event
{
    private float $firedAt;

    public function __construct()
    {
        $this->firedAt = microtime(true);
    }

    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getFiredAt(): float
    {
        return $this->firedAt;
    }

    abstract public function toArray(): array;
}
