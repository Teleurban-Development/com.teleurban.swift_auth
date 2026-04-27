<?php

namespace Equidna\Toolkit\Exceptions;

use RuntimeException;
use Throwable;

class NotFoundException extends RuntimeException
{
    /** @var array<string, mixed> */
    protected array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message = '',
        array $errors = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
