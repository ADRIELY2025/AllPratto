<?php

declare(strict_types=1);

namespace App\Trait;

trait DatabaseValueNormalizer
{
    public function convertDateToBrFormat(?string $date): string
    {
        $now = new \DateTimeImmutable();
        if ($date === null || trim($date) === '') {
            return $now->format('d/m/Y H:i:s');
        }
        $formats = [
            # Database formats
            'Y-m-d H:i:s.u',
            'Y-m-d H:i:s',
            'Y-m-d',

            # BR formats
            'd/m/Y H:i:s',
            'd/m/Y',
        ];
        foreach ($formats as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $date);
            if ($dateTime !== false) {
                # Decide output format based on input granularity
                return str_contains($format, 'H')
                    ? $dateTime->format('d/m/Y H:i:s')
                    : $dateTime->format('d/m/Y');
            }
        }
        # Fallback seguro
        return $now->format('d/m/Y H:i:s');
    }
    public function convertBrDateToDatabaseFormat(?string $date): string
    {
        $now = new \DateTimeImmutable();
        if ($date === null || trim($date) === '') {
            return $now->format('Y-m-d H:i:s');
        }
        $formats = [
            # BR datetime com e sem microsegundos
            'd/m/Y H:i:s.u',
            'd/m/Y H:i:s',
            # BR date
            'd/m/Y',
        ];
        foreach ($formats as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $date);
            if ($dateTime !== false) {
                return str_contains($format, 'H')
                    ? $dateTime->format('Y-m-d H:i:s')
                    : $dateTime->format('Y-m-d');
            }
        }
        # Fallback seguro
        return $now->format('Y-m-d H:i:s');
    }
    public function normalizeToFloat(string|int|float|null $value): float
    {
        # Fast-path para tipos numéricos reais
        if (is_int($value) || is_float($value)) {
            if (!is_finite((float)$value)) {
                return 0.0;
            }
            return (float)$value;
        }
        $raw = trim($value);
        if ($raw === '') {
            return 0.0;
        }
        # 1. Remove símbolos de moeda e tudo que não seja dígito, ponto, vírgula ou sinal. Mantemos apenas: 0-9 . , - +
        $clean = preg_replace('/[^\d.,+-]/u', '', $raw);
        if ($clean === null || $clean === '') {
            return 0.0;
        }
        # 2. Validação básica: no máximo um sinal (+/-) no início
        if (preg_match('/^[+-]?[\d.,]+$/', $clean) !== 1) {
            return 0.0;
        }
        $dotCount   = substr_count($clean, '.');
        $commaCount = substr_count($clean, ',');
        $decimalSeparator = null;
        if ($dotCount > 0 && $commaCount > 0) {
            # Ambos presentes: último define o decimal
            $lastDot   = strrpos($clean, '.');
            $lastComma = strrpos($clean, ',');
            $decimalSeparator = ($lastDot > $lastComma) ? '.' : ',';
        } elseif ($dotCount === 1 && $commaCount === 0) {
            $decimalSeparator = self::isValidSingleDecimal($clean, '.') ? '.' : null;
        } elseif ($commaCount === 1 && $dotCount === 0) {
            $decimalSeparator = self::isValidSingleDecimal($clean, ',') ? ',' : null;
        } elseif ($dotCount === 0 && $commaCount === 0) {
            # Número inteiro puro
            $decimalSeparator = null;
        } else {
            # Múltiplos separadores do mesmo tipo => inconsistente
            return 0.0;
        }
        # 4. Normalização: Remove separadores de milhar Converte separador decimal para ponto
        if ($decimalSeparator !== null) {
            $thousandSeparator = ($decimalSeparator === '.') ? ',' : '.';
            # Remove separadores de milhar
            $normalized = str_replace($thousandSeparator, '', $clean);
            # Troca decimal para ponto
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        } else {
            # Sem decimal, apenas remover qualquer separador remanescente
            $normalized = str_replace([',', '.'], '', $clean);
        }
        # 5. Validação final: formato numérico estrito
        if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $normalized)) {
            return 0.0;
        }
        $floatValue = (float)$normalized;
        if (!is_finite($floatValue)) {
            return 0.0;
        }
        return $floatValue;
    }
    # Valida se um único separador pode ser considerado decimal Regra pragmática:
    # Exatamente 2 dígitos após o separador => decimal válido
    private static function isValidSingleDecimal(string $value, string $separator): bool
    {
        $pos = strrpos($value, $separator);
        if ($pos === false) {
            return false;
        }
        $after = substr($value, $pos + 1);
        #Aceita apenas se houver exatamente 2 dígitos após (padrão monetário)
        return preg_match('/^\d{2}$/', $after) === 1;
    }
    public function normalizeEmpty($value): ?string
    {
        if (!isset($value)) {
            return null;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}