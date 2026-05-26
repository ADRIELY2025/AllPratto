<?php

class Print
{
    private ?string $html = null;

    private array $options = [
        'marginsType' => 0,
        'pageSize' => 'A4',
        'printBackground' => true,
        'landscape' => false,
    ];

    public static function create(): self
    {
        return new self();
    }

    public function stringHTML(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    public function print(): string
    {
        if ($this->html === null) {
            throw new RuntimeException('O HTML não foi definido. Use stringHTML() antes de chamar print().');
        }

        $sessionId = (string) round(microtime(true) * 1000);
        $pdfFileName = "relatorio_{$sessionId}.pdf";
        $pdfPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $pdfFileName;
        $htmlPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "print_{$sessionId}.html";

        file_put_contents($htmlPath, $this->html);

        $chromeBinary = $this->locateChromeBinary();
        if ($chromeBinary === null) {
            unlink($htmlPath);
            throw new RuntimeException('Chrome/Chromium não foi encontrado no PATH ou nos locais padrão.');
        }

        $command = escapeshellarg($chromeBinary)
            . ' --headless --disable-gpu --no-sandbox --disable-dev-shm-usage'
            . ' --print-to-pdf=' . escapeshellarg($pdfPath)
            . ' ' . escapeshellarg($htmlPath);

        exec($command . ' 2>&1', $output, $exitCode);
        unlink($htmlPath);

        if ($exitCode !== 0 || !file_exists($pdfPath)) {
            throw new RuntimeException('Falha ao gerar PDF: ' . implode("\n", $output));
        }

        return $pdfPath;
    }

    private function locateChromeBinary(): ?string
    {
        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            return $this->locateChromeOnWindows();
        }

        $binaries = ['google-chrome', 'chrome', 'chromium-browser', 'chromium'];
        foreach ($binaries as $binary) {
            $path = $this->findUnixBinary($binary);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    private function locateChromeOnWindows(): ?string
    {
        $possiblePaths = [
            getenv('PROGRAMFILES') . '\\Google\\Chrome\\Application\\chrome.exe',
            getenv('PROGRAMFILES(X86)') . '\\Google\\Chrome\\Application\\chrome.exe',
            getenv('PROGRAMFILES') . '\\Chromium\\Application\\chrome.exe',
            getenv('PROGRAMFILES(X86)') . '\\Chromium\\Application\\chrome.exe',
        ];

        foreach ($possiblePaths as $path) {
            if ($path !== false && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findUnixBinary(string $binary): ?string
    {
        exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $output, $exitCode);
        if ($exitCode === 0 && !empty($output[0])) {
            return trim($output[0]);
        }

        return null;
    }
}
