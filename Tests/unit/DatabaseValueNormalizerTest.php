<?php

declare(strict_types=1);

# Classe concreta auxiliar para testar a trait isoladamente
class NormalizerStub
{
    use App\Trait\DatabaseValueNormalizer;
}

# Instancia o stub antes de cada teste
beforeEach(function () {
    $this->normalizer = new NormalizerStub();
});

# Teste 1: método mais crítico — erros aqui causam cálculos financeiros errados
test('normalizeToFloat converte corretamente os principais formatos numéricos', function () {
    # Tipos nativos passam direto
    expect($this->normalizer->normalizeToFloat(42))->toBe(42.0);
    expect($this->normalizer->normalizeToFloat(3.14))->toBe(3.14);

    # Formato brasileiro: vírgula decimal, ponto milhar
    expect($this->normalizer->normalizeToFloat('1.234,56'))->toBe(1234.56);
    expect($this->normalizer->normalizeToFloat('R$ 99,90'))->toBe(99.90);

    # Formato americano: ponto decimal, vírgula milhar
    expect($this->normalizer->normalizeToFloat('1,234.56'))->toBe(1234.56);

    # Entradas inválidas retornam 0.0 como fallback seguro
    expect($this->normalizer->normalizeToFloat(''))->toBe(0.0);
    expect($this->normalizer->normalizeToFloat('abc'))->toBe(0.0);
});

# Teste 2: ida e volta banco ↔ BR — erros aqui corrompem datas no banco
test('conversão de datas ida e volta preserva o valor original', function () {
    $dataBanco = '2025-06-15 08:45:00';
    $dataBr = '15/06/2025 08:45:00';

    # Banco → BR → Banco retorna o mesmo valor
    expect($this->normalizer->convertBrDateToDatabaseFormat(
        $this->normalizer->convertDateToBrFormat($dataBanco)
    ))->toBe($dataBanco);

    # BR → Banco → BR retorna o mesmo valor
    expect($this->normalizer->convertDateToBrFormat(
        $this->normalizer->convertBrDateToDatabaseFormat($dataBr)
    ))->toBe($dataBr);
});