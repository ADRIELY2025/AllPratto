<?php

declare(strict_types=1);

namespace app\helpers;

final class Vite
{

    # Arquivo criado pelo 'vite dev' — presença indica servidor de desenvolvimento ativo
    private const HOT  = __DIR__ . '/../../public/hot';
    # Manifest gerado pelo 'npm run build' — mapeia entrypoints para arquivos com hash
    private const MAN  = __DIR__ . '/../../public/assets/manifest.json';
    # Prefixo das URLs públicas dos assets servidos pelo Nginx em produção
    private const BASE = '/assets/';

    # Cache estático do manifest — leitura de disco ocorre apenas uma vez por processo
    private static ?array $manifest = null;
    # Garante que o cliente HMR do Vite seja injetado somente uma vez por requisição
    private static bool $injected = false;

    # Ponto de entrada público — valida entrypoints e delega para o modo correto
    public static function tag(string ...$entries): string
    {
        # Fail Fast: recusa lista vazia antes de qualquer processamento
        if (!$entries) throw new \InvalidArgumentException('Vite::tag() exige ao menos um entrypoint.');
        # Fail Fast: recusa entrypoints em branco individualmente
        foreach ($entries as $e) if (trim($e) === '') throw new \InvalidArgumentException('Entrypoint vazio passado para Vite::tag().');
        # Detecta o ambiente pelo arquivo 'hot' e despacha para o renderizador correto
        return self::isHot() ? self::renderHot($entries) : self::renderBuild($entries);
    }
    # Retorna true quando o arquivo 'hot' existe no disco — dev server do Vite ativo
    public static function isHot(): bool
    {
        return file_exists(self::HOT);
    }
    # Modo dev: injeta o cliente HMR uma vez e aponta cada entrypoint para o dev server
    private static function renderHot(array $entries): string
    {
        # Lê a URL base do arquivo 'hot'; usa localhost:5173 como fallback seguro
        $url = trim((string) @file_get_contents(self::HOT)) ?: 'http://localhost:5173';
        # Injeta @vite/client (necessário para HMR) apenas na primeira chamada do processo
        $out = !self::$injected && (self::$injected = true) ? "<script type=\"module\" src=\"{$url}/@vite/client\"></script>\n    " : '';
        # Acrescenta uma tag <script type="module"> por entrypoint apontando para o dev server
        return $out . implode("\n    ", array_map(fn($e) => "<script type=\"module\" src=\"{$url}/{$e}\"></script>", $entries));
    }
    # Modo build: resolve arquivos com hash via manifest e gera CSS → modulepreload → JS
    private static function renderBuild(array $entries): string
    {
        # Decodifica o manifest uma única vez e armazena em cache estático
        $m = self::$manifest ??= json_decode((string) file_get_contents(self::MAN), true, 512, JSON_THROW_ON_ERROR);
        # Acumuladores separados: $css, $pre e $js garantem a ordem correta na saída HTML
        [$css, $pre, $js, $sc, $sp] = [[], [], [], [], []];
        # Valida cada entrypoint no manifest e coleta suas tags e dependências
        foreach ($entries as $e) isset($m[$e])
            ? self::collect($m, $e, $css, $pre, $js, $sc, $sp)
            : throw new \RuntimeException("Entrypoint '{$e}' não encontrado em manifest.json. Execute 'npm run build'.");
        # CSS antes evita FOUC; modulepreload antes de JS permite carregamento paralelo
        return implode("\n    ", array_merge($css, $pre, $js));
    }
    # Coleta recursivamente CSS, script/preload e imports do manifest
    private static function collect(array $m, string $key, array &$css, array &$pre, array &$js, array &$sc, array &$sp, bool $isEntry = true): void
    {
        # Guard aborta silenciosamente se o chunk não existir — manifest pode ter referências opcionais
        if (!$chunk = ($m[$key] ?? null)) return;
        # Registra o CSS direto do chunk $sc (seen CSS) previne <link> duplicados entre chunks
        foreach ($chunk['css'] ?? [] as $c) $sc[$c] ??= ($css[] = sprintf('<link rel="stylesheet" href="%s%s">', self::BASE, $c));
        # Arquivo principal: CSS <link>, entry JS <script>, import JS <modulepreload>
        if ($f = $chunk['file'] ?? '') {
            if (str_ends_with($f, '.css')) {
                $sc[$f] ??= ($css[] = sprintf('<link rel="stylesheet" href="%s%s">', self::BASE, $f));
            } elseif ($isEntry) {
                $js[] = sprintf('<script type="module" src="%s%s"></script>', self::BASE, $f);
            } else {
                $pre[] = sprintf('<link rel="modulepreload" href="%s%s">', self::BASE, $f);
            }
        }
        # Itera os imports estáticos do chunk para adicionar modulepreload das dependências
        foreach ($chunk['imports'] ?? [] as $ik) {
            # Pula chunk já visitado — previne loops infinitos em imports circulares
            if ($sp[$ik] ?? false) continue;
            # Marca como visitado antes da recursão para garantir passagem única
            $sp[$ik] = true;
            # Recursão com $isEntry = false dependências viram modulepreload, não <script>
            self::collect($m, $ik, $css, $pre, $js, $sc, $sp, false);
        }
    }
}
