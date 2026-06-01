<?php

declare(strict_types=1);

namespace App\Trait;

trait Response
{
    public function json(
        $response,
        array $data,
        int $statusCode = 200
    ) {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $response->getBody()->write($json);

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json');
    }
}
