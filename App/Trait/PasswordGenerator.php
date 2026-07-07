<?php

declare(strict_types=1);

namespace App\Trait;

trait PasswordGenerator
{
    // -------------------------------------------------------------------------
    // Gera uma senha aleatória e segura (12 caracteres por padrão), usando um
    // gerador criptograficamente seguro (random_int) e um alfabeto sem
    // caracteres ambíguos (ex.: 0/O, 1/l/I), para facilitar a digitação manual
    // se preciso. Garante ao menos um caractere de cada grupo (maiúscula,
    // minúscula, número e símbolo).
    // -------------------------------------------------------------------------
    public function gerarSenhaSegura(int $tamanho = 12): string
    {
        $letrasMaiusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $letrasMinusculas = 'abcdefghjkmnpqrstuvwxyz';
        $numeros          = '23456789';
        $simbolos         = '!@#$%&*+-=';
        $alfabetoCompleto = $letrasMaiusculas . $letrasMinusculas . $numeros . $simbolos;

        $senha   = [];
        $senha[] = $letrasMaiusculas[random_int(0, strlen($letrasMaiusculas) - 1)];
        $senha[] = $letrasMinusculas[random_int(0, strlen($letrasMinusculas) - 1)];
        $senha[] = $numeros[random_int(0, strlen($numeros) - 1)];
        $senha[] = $simbolos[random_int(0, strlen($simbolos) - 1)];

        for ($i = count($senha); $i < $tamanho; $i++) {
            $senha[] = $alfabetoCompleto[random_int(0, strlen($alfabetoCompleto) - 1)];
        }

        shuffle($senha);

        return implode('', $senha);
    }
}
