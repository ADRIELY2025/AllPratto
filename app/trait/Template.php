<?php

declare(strict_types=1);

namespace App\Trait;

use Slim\Views\Twig;
use Twig\TwigFunction;
use app\helpers\Vite;

trait Template
{
    private ?Twig $twig = null;

    private function createTwig(): Twig
    {
        if ($this->twig !== null) {
            return $this->twig;
        }

        $this->twig = Twig::create(DIR_VIEWS);
        $env = $this->twig->getEnvironment();
        $env->addGlobal('icon', '/img/favicon.png');

        // Função vite(...$entries) registrada para uso em templates Twig.
        // is_safe=html → o Twig NÃO escapa o HTML retornado (são tags válidas).
        $env->addFunction(new TwigFunction(
            'vite',
            static fn(string ...$entries): string => Vite::tag(...$entries),
            ['is_safe' => ['html']]
        ));

        return $this->twig;
    }

    public function getTwig(): Twig
    {
        return $this->createTwig();
    }

    public function getHtml(string $name, array $data = []): string
    {
        return $this->createTwig()->fetch($name, $data);
    }

    public function setView(string $name): string
    {
        return 'pages/' . $name . EXT_VIEWS;
    }
}
