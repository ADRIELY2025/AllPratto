<?php

declare(strict_types=1);

namespace App\Helpers;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class QrCodeGenerator
{
    /**
     * Gera um arquivo QR Code para uma mesa
     *
     * @param int $mesaId ID da mesa
     * @param int $mesaNumero Número da mesa
     * @return bool true se gerado com sucesso
     */
    public static function generateForMesa(int $mesaId, int $mesaNumero): bool
    {
        try {
            // URL que será aberta pelo QRCode
            $protocol = (php_sapi_name() === 'cli') ? 'http' : (PROTOCOL ?? 'http');
            $host = (php_sapi_name() === 'cli') ? 'localhost' : HOST;
            $qrUrl = $protocol . '://' . $host . '/cardapio/mesa/' . $mesaNumero;

            $qrDir = ROOT . '/storage/qrcode/' . $mesaId;

            if (!is_dir($qrDir)) {
                mkdir($qrDir, 0775, true);
            }

            $fileName = "mesa_{$mesaId}.png";

            $writer = new PngWriter();

            // Create QR code
            $qrCode = new QrCode(
                data: $qrUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );

            $result = $writer->write($qrCode);

            // Validate the result
            $writer->validateResult($result, $qrUrl);

            $result->saveToFile($qrDir . '/' . $fileName);

            return true;
        } catch (\Throwable $e) {
            echo "❌ Erro ao gerar QR code para mesa {$mesaId}: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
