<?php

declare(strict_types=1);

namespace app\controller;


final class Home extends Base
{
    public function home($request, $response)
    {
        try {

            return $this->getTwig()
                ->render($response, $this->setView('home'), [
                    'titulo' => 'Início',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
        }
    }
}
