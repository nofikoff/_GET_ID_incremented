<?php

namespace App\Domain\Sequence\Exceptions;

use RuntimeException;

/**
 * A request the registry refuses on its merits. REST renders it as the contract's DomainError
 * (contracts/rest-api.yaml), MCP as a tool error led by the same code, so both transports name
 * one refusal the same way.
 */
abstract class DomainRejection extends RuntimeException
{
    /**
     * @param  array<string, string>  $context  fields the contract places next to the code, e.g. project_key and type
     */
    final protected function __construct(string $message, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    /**
     * The value of DomainError.error.code.
     */
    abstract public function errorCode(): string;

    /**
     * @return array<string, string>
     */
    public function context(): array
    {
        return $this->context;
    }
}
