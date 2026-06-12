<?php

declare(strict_types=1);

namespace App\Library;

final class Mesa
{
    public function __construct(
        private int $numero,
        private string $status = 'livre',
        private bool $ativo = true
    ) {
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function estaAtiva(): bool
    {
        return $this->ativo;
    }

    public function estaDisponivel(): bool
    {
        return $this->ativo
            && $this->status === 'livre';
    }

    public function podeReceberPedido(): bool
    {
        return $this->ativo
            && $this->status === 'livre';
    }
}