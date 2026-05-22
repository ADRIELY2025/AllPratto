<?php

declare(strict_types=1);

namespace app\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Slim\Psr7\Response;

class Middleware
{
    #Metodo de autenticação via token de rota POST.
    public static function api()
    {
        $middleware = function ($request, $handler) {
            #Lê o JWT gravado pelo método auth() no cookie httponly do navegador.
            $token = $_COOKIE['auth_token'] ?? null;
            try {
                #Curto-circuito: exige cookie presente e flag de sessão antes do decode.
                if (!$token || empty($_SESSION['user']['logado'])) throw new \RuntimeException();
                #Valida assinatura HS256 e expiração do payload contra a SECRET_KEY.
                JWT::decode($token, new Key(SECRET_KEY, 'HS256'));
            } catch (\Throwable $e) {
                #Qualquer falha cai aqui: cookie ausente, expirado ou adulterado.
                $response = new Response();
                #Resposta JSON padronizada no mesmo contrato usado pelo Login::auth.
                $response->getBody()->write(json_encode(['status' => false, 'msg' => 'Sessão expirada ou não autenticada.', 'id' => 0]));
                #Status 401 é o código semântico correto para credencial inválida.
                return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
            }
            #Credenciais íntegras: encaminha a requisição ao próximo handler do Slim.
            return $handler->handle($request);
        };
        return $middleware;
    }
    #Metodo de autenticação das rotas GET
    public static function web()
    {
        $middleware = function ($request, $handler) {
            #Lê o JWT gravado pelo método auth() no cookie httponly do navegador.
            $token = $_COOKIE['auth_token'] ?? null;
            #Verifica se a rota atual é a própria página de login para inverter a regra.
            $isLogin = $request->getUri()->getPath() === '/login';
            #Sinalizador que indica se o usuário possui sessão e token válidos no momento.
            $auth = false;
            try {
                #Curto-circuito: só faz decode se cookie e flag de sessão estiverem presentes.
                if ($token && !empty($_SESSION['user']['logado'])) {
                    #Valida assinatura HS256 e expiração do payload contra a SECRET_KEY.
                    JWT::decode($token, new Key(SECRET_KEY, 'HS256'));
                    #Token íntegro e sessão ativa: marca o usuário como autenticado.
                    $auth = true;
                }
            } catch (\Throwable $e) {
                #Qualquer falha é silenciosamente tratada como usuário não autenticado.
            }
            #Já autenticado tentando ver a tela de login → manda direto para a home.
            if ($isLogin && $auth) return $handler->handle($request)->withHeader('Location', '/home')->withStatus(302);
            #Não autenticado tentando acessar rota protegida → manda para a tela de login.
            if (!$isLogin && !$auth) return $handler->handle($request)->withHeader('Location', '/login')->withStatus(302);
            #Caso permitido: autenticado em rota privada ou anônimo na própria /login.
            return $handler->handle($request);
        };
        return $middleware;
    }
}