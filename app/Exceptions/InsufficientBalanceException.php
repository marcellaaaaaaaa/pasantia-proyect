<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(
            "Saldo insuficiente: se solicitaron {$requested}, disponible {$available}."
        );
    }
}
