<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ErrorCode;

/**
 * Domain-level exception that always maps cleanly onto the API envelope.
 * Controllers / services should `throw` these instead of returning JSON.
 */
class ApiException extends \RuntimeException
{
    /** @var array<string, mixed> */
    protected array $details;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? $errorCode->defaultMessage(),
            $errorCode->httpStatus(),
            $previous,
        );
        $this->details = $details;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }

    public function httpStatus(): int
    {
        return $this->errorCode->httpStatus();
    }
}
