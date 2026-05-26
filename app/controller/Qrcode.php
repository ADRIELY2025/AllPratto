<?php

declare(strict_types=1);

namespace app\controller;

final class QrCode extends Base
{
    // Limite máximo de mesas permitido
    private const MESA_MAX = 10;

    // ─────────────────────────────────────────────────────────────
    //  GET /qrcode
    //  Painel com todos os QR Codes das mesas (1 a 10)
    // ─────────────────────────────────────────────────────────────
    public function index($request, $response)
    {
        $mesas = range(1, self::MESA_MAX);

        return $this->getTwig()
            ->render($response, $this->setView('qrcode'), [
                'titulo' => 'QR Codes das Mesas',
                'mesas'  => $mesas,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }

    // ─────────────────────────────────────────────────────────────
    //  GET /qrcode/{mesa}
    //  QR Code individual de uma mesa para impressão
    // ─────────────────────────────────────────────────────────────
    public function mesa($request, $response, $args)
    {
        $mesa = (int) ($args['mesa'] ?? 0);

        // Valida limite
        if ($mesa < 1 || $mesa > self::MESA_MAX) {
            return $this->getTwig()
                ->render($response, $this->setView('qrcode'), [
                    'titulo' => 'QR Codes das Mesas',
                    'mesas'  => range(1, self::MESA_MAX),
                    'erro'   => "Mesa {$mesa} inválida. O limite é de " . self::MESA_MAX . " mesas.",
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(400);
        }

        return $this->getTwig()
            ->render($response, $this->setView('qrcode-print'), [
                'titulo' => "QR Code — Mesa {$mesa}",
                'mesa'   => $mesa,
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
}
