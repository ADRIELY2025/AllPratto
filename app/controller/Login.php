<?php

namespace app\controller;

final class Login extends Base
{
    // -------------------------------------------------------------------------
    // Renderiza a página de login
    // -------------------------------------------------------------------------
    public function login($request, $response)
    {
        try {
            $csrf = $_COOKIE['g_csrf_token'] ?? bin2hex(random_bytes(16));
            setcookie('g_csrf_token', $csrf, [
                'expires'  => time() + 3600,
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);

            return $this->getTwig()
                ->render($response, $this->setView('login'), [
                    'titulo'            => 'Início',
                    'google_client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
                ])
                ->withHeader('Content-Type', 'text/html')
                ->withStatus(200);
        } catch (\Exception $e) {
            error_log('[login][view] ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Autenticação via CPF / e-mail / WhatsApp + senha
    // -------------------------------------------------------------------------
    public function authenticate($request, $response)
    {
        $form  = $request->getParsedBody();
        $login = $form['login'] ?? null;
        $senha = $form['senha'] ?? null;

        # Bloqueia se algum campo veio vazio
        if ($login === null || $senha === null) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Por favor informe seu usuário e senha!',
                'id'     => 0,
            ], 403);
        }

        try {
            # Monta a query parametrizada (proteção contra SQL injection via Doctrine)
            $query = \app\database\DB::select()->from('vw_user');

            $query->where('cpf = :login')
                ->orWhere('email = :login')
                ->orWhere('telefone = :login');

            $query->setParameter('login', $login);
            $user = $query->fetchAssociative();

            if (!$user or $user['ativo'] == false) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Sua conta não está ativa no momento. Entre em contato com o administrador.',
                    'id'     => 0,
                ], 403);
            }

            # Hash inválido usado quando o usuário não existe (proteção contra timing attack)
            $dummyHash   = '$2y$10$CwTycUXWue0Thq9StjUM0uJ8.k3.kK1m3Sv7lJ1uG9N9Yvb.MqYsa';
            $senhaValida = password_verify($senha, $user['senha'] ?? $dummyHash);

            # Falha de autenticação: mensagem genérica + contador de tentativas
            if (!$senhaValida) {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;

                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_locked_until'] = time() + 900; // 15 minutos
                    $_SESSION['login_attempts']     = 0;
                }

                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Verifique seu e-mail e senha e tente novamente!',
                    'id'     => 0,
                ], 403);
            }

            # Login válido: zera contadores de tentativa e lockout
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            # Regenera o ID da sessão para mitigar session fixation
            session_regenerate_id(true);

            # Renova o hash se o algoritmo/custo padrão tiver mudado
            if (password_needs_rehash($user['senha'], PASSWORD_DEFAULT)) {
                \app\database\DB::connection()->update(
                    'users',
                    [
                        'senha'         => password_hash($senha, PASSWORD_DEFAULT),
                        'atualizado_em' => date('Y-m-d H:i:s'),
                    ],
                    ['id' => $user['id']],
                );
            }

            # Remove o hash da senha antes de gravar na sessão
            unset($user['senha']);

            return $this->_criarSessaoERetornar($response, $user);
        } catch (\PDOException $e) {
            error_log('[auth][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\UnexpectedValueException | \DomainException $e) {
            error_log('[auth][JWT] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível concluir o login. Tente novamente.', 'id' => 0], 500);
        } catch (\Throwable $e) {
            error_log('[auth][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.', 'id' => 0], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Autenticação via Google (League OAuth2 + tokeninfo endpoint)
    // -------------------------------------------------------------------------
    public function google($request, $response)
    {
        $form                = $request->getParsedBody();
        $credential          = trim((string) ($form['credential']   ?? ''));
        $form_g_csrf_token   = trim((string) ($form['g_csrf_token'] ?? ''));
        $cookie_g_csrf_token = $_COOKIE['g_csrf_token'] ?? null;
        $google_client_id    = trim($_ENV['GOOGLE_CLIENT_ID'] ?? '');
        $google_client_secret = trim($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

        # Verifica dados ausentes
        if ($credential === '' || $form_g_csrf_token === '' || $cookie_g_csrf_token === null) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Credenciais Google ausentes.',
                'id'     => 0,
            ], 400);
        }

        if ($google_client_id === '') {
            error_log('[google][CONFIG] GOOGLE_CLIENT_ID está ausente.');
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Configuração do Google Sign-In ausente.',
                'id'     => 0,
            ], 500);
        }

        # Validação CSRF: token do formulário deve bater com o cookie
        if (!hash_equals($cookie_g_csrf_token, $form_g_csrf_token)) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Falha na verificação de segurança (CSRF).',
                'id'     => 0,
            ], 400);
        }

        try {
            # Usa o cliente HTTP do League OAuth2 para validar o token
            # diretamente no endpoint oficial do Google
            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $google_client_id,
                'clientSecret' => $google_client_secret,
                'redirectUri'  => '',
            ]);

            try {
                $httpResponse = $provider->getHttpClient()->request(
                    'GET',
                    'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
                    ['timeout' => 3, 'connect_timeout' => 2]
                );
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $errorBody = (string) ($e->getResponse()?->getBody() ?? '');
                error_log('[google][HTTP] ' . $e->getMessage() . ' | body=' . $errorBody);

                $message = 'Falha ao validar token Google. Tente novamente.';
                if ($errorBody !== '') {
                    try {
                        $payload = json_decode($errorBody, true, 512, JSON_THROW_ON_ERROR);
                        if (!empty($payload['error_description'])) {
                            $message = $payload['error_description'];
                        } elseif (!empty($payload['error'])) {
                            $message = is_string($payload['error']) ? $payload['error'] : json_encode($payload['error']);
                        }
                    } catch (\JsonException $_) {
                        // ignora, manterá a mensagem genérica
                    }
                }

                return $this->json($response, ['status' => false, 'msg' => $message, 'id' => 0], 502);
            }

            $claims = json_decode(
                (string) $httpResponse->getBody(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            # Verifica se o token foi emitido para o client_id correto (proteção contra tokens de outras apps)
            if (($claims['aud'] ?? '') !== $google_client_id) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Token do Google inválido.',
                    'id'     => 0,
                ], 401);
            }

            $email = $claims['email'] ?? null;

            if (!($claims['email_verified'] ?? false)) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'O e-mail Google não foi verificado.',
                    'id'     => 0,
                ], 403);
            }

            if (!$email) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'E-mail não encontrado no token Google.',
                    'id'     => 0,
                ], 400);
            }

            # Busca o usuário pelo e-mail na view vw_user
            $qb = \app\database\DB::select('*')->from('vw_user');
            $qb->where('email = ' . $qb->createNamedParameter($email));
            $user = $qb->fetchAssociative();

            # Usuário não cadastrado no sistema: cria registro automático pelo Google.
            if (!$user) {
                $givenName  = trim((string) ($claims['given_name'] ?? ''));
                $familyName = trim((string) ($claims['family_name'] ?? ''));
                $googleSub  = trim((string) ($claims['sub'] ?? ''));
                $nome       = $givenName ?: 'Usuário';
                $sobrenome  = $familyName ?: 'Google';
                $cpf        = $googleSub ? 'google:' . $googleSub : 'google:' . md5($email);
                $senhaHash  = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

                $connection = \app\database\DB::connection();
                $insertData = [
                    'nome'           => $nome,
                    'sobrenome'      => $sobrenome,
                    'cpf'            => $cpf,
                    'rg'             => '',
                    'senha'          => $senhaHash,
                    'ativo'          => 1,
                    'administrador'  => 0,
                ];

                error_log('[google][INSERT_USERS_DATA] ' . json_encode($insertData));

                $connection->insert('users', $insertData);

                $id_usuario = (int) $connection->lastInsertId();
                if (!$id_usuario) {
                    throw new \RuntimeException("Não foi possível obter ID do usuário");
                }

                if ($email) {
                    $connection->insert('contact', [
                        'id_usuario' => $id_usuario,
                        'tipo'       => 'EMAIL',
                        'contato'    => $email,
                    ]);
                }

                $user = [
                    'id'            => $id_usuario,
                    'nome'          => $nome,
                    'sobrenome'     => $sobrenome,
                    'cpf'           => $cpf,
                    'rg'            => '',
                    'senha'         => '',
                    'ativo'         => true,
                    'administrador' => false,
                    'email'         => $email,
                    'celular'       => null,
                    'telefone'      => null,
                    'whatsapp'      => null,
                ];
            }

            # Conta inativa: aguarda aprovação do administrador
            if (!$user['ativo']) {
                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'Sua conta não está ativa no momento. Entre em contato com o administrador.',
                    'id'     => 0,
                ], 403);
            }

            # Remove o hash da senha antes de gravar na sessão
            unset($user['senha']);

            return $this->_criarSessaoERetornar($response, $user);
        } catch (\JsonException $e) {
            error_log('[google][JSON] ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $msg = 'Resposta inválida do Google. Tente novamente.';
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $msg = $e->getMessage();
            }
            return $this->json($response, ['status' => false, 'msg' => $msg, 'id' => 0], 502);
        } catch (\PDOException $e) {
            error_log('[google][DB] ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $msg = 'Não foi possível concluir o login. Tente novamente.';
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $msg = $e->getMessage();
            }
            return $this->json($response, ['status' => false, 'msg' => $msg, 'id' => 0], 500);
        } catch (\Throwable $e) {
            error_log('[google][GERAL] ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $msg = 'Falha na autenticação com Google. Tente novamente.';
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                $msg = $e->getMessage();
            }
            return $this->json($response, ['status' => false, 'msg' => $msg, 'id' => 0], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Pré-cadastro de usuário
    // -------------------------------------------------------------------------
    public function preRegister($request, $response)
    {
        $form      = $request->getParsedBody();
        $nome      = $form['cad-nome'] ?? null;
        $sobrenome = $form['cad-sobrenome'] ?? null;
        $cpf       = $form['cad-cpf']       ?? null;
        $rg        = $form['cad-rg']        ?? null;
        $senha     = $form['cad-senha']          ?? '';
        $confirmar_senha = $form['cad-confirmar-senha'] ?? '';
        $contacts  = $form['contacts']       ?? [];
        $email     = $form['cad-email']     ?? null;
        $telefone  = $form['cad-telefone']  ?? null;
        $celular   = $form['cad-celular']   ?? null;
        $whatsapp  = $form['cad-whatsapp']  ?? null;

        # Adiciona e-mail e telefone ao array de contatos, se informados
        if ($email) {
            $contacts[] = ['tipo' => 'EMAIL',    'contato' => $email];
        }
        if ($telefone) {
            $contacts[] = ['tipo' => 'TELEFONE', 'contato' => $telefone];
        }
        if ($celular) {
            $contacts[] = ['tipo' => 'CELULAR',  'contato' => $celular];
        }
        if ($whatsapp) {
            $contacts[] = ['tipo' => 'WHATSAPP', 'contato' => $whatsapp];
        }

        # Campos obrigatórios
        if (!$nome || !$sobrenome || !$cpf || !$senha) {
            return $this->json($response, [
                'status' => false,
                'msg'    => 'Nome, sobrenome, CPF e senha são obrigatórios.',
            ], 400);
        }

        try {
            $qb = \app\database\DB::select('id')->from('users');

            $qb->where('cpf = ' . $qb->createNamedParameter($cpf));

            $exists = $qb->fetchAssociative();

            if ($exists) {

                return $this->json($response, [
                    'status' => false,
                    'msg'    => 'CPF já cadastrado.',
                ], 409);
            }

            $connection = \app\database\DB::connection();

            $connection->insert('users', [
                'nome'      => $nome,
                'sobrenome' => $sobrenome,
                'cpf'       => $cpf,
                'rg'        => $rg,
                'senha'     => password_hash($confirmar_senha, PASSWORD_DEFAULT),
                'ativo'     => 0,
            ]);

            $id_usuario = (int) $connection->lastInsertId();

            if (!$id_usuario) {
                throw new \RuntimeException("Não foi possível obter ID do usuário");
            }

            # Insere cada contato validado
            foreach ($contacts as $contact) {
                $tipo    = strtoupper(trim($contact['tipo']    ?? ''));
                $contato = trim($contact['contato'] ?? '');

                if (!$tipo || !$contato) {
                    continue;
                }

                if (!in_array($tipo, ['EMAIL', 'CELULAR', 'TELEFONE', 'WHATSAPP'], true)) {
                    continue;
                }

                \app\database\DB::connection()->insert('contact', [
                    'id_usuario' => $id_usuario,
                    'tipo'       => $tipo,
                    'contato'    => $contato,
                ]);
            }

            return $this->json($response, [
                'status' => true,
                'msg'    => 'Usuário cadastrado com sucesso!',
            ], 200);
        } catch (\PDOException $e) {
            error_log('[preRegister][DB] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Não foi possível realizar o cadastro. Tente novamente.'], 500);
        } catch (\Throwable $e) {
            error_log('[preRegister][GERAL] ' . $e->getMessage());
            return $this->json($response, ['status' => false, 'msg' => 'Erro inesperado. Tente novamente.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Logout: marca inativo no banco, destrói sessão e apaga cookies
    // -------------------------------------------------------------------------
    public function logout($request, $response)
    {
        # Pega o ID do usuário ANTES de destruir a sessão
        $userId = $_SESSION['user']['id'] ?? null;

        if ($userId) {

            try {

                \app\database\DB::connection()->update(
                    'users',
                    [
                        'ativo'         => 0,
                        'atualizado_em' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'id' => (int) $userId
                    ]
                );
            } catch (\Throwable $e) {

                error_log('[logout][DB] ' . $e->getMessage());
            }
        }

        # HTTPS detect
        $isSecure =
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;

        $cookieDomain = parse_url(HOST, PHP_URL_HOST) ?: '';

        # Remove cookie da sessão PHP
        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        # Remove JWT
        setcookie('auth_token', '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'domain'   => $cookieDomain,
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        # Remove CSRF cookie do Google
        setcookie('g_csrf_token', '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'domain'   => $cookieDomain,
            'secure'   => $isSecure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        # Limpa e destrói a sessão (ordem correta)
        $_SESSION = [];
        session_unset();
        session_destroy();

        return (new \Slim\Psr7\Response())
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
    // =========================================================================
    // HELPER PRIVADO
    // Centraliza a criação de sessão + JWT + cookie, evitando duplicação
    // entre authenticate() e google()
    // =========================================================================
    private function _criarSessaoERetornar($response, array $user)
    {
        session_regenerate_id(true);

        $_SESSION['user']           = $user;
        $_SESSION['user']['logado'] = true;

        $lifetime = (int) (ini_get('session.gc_maxlifetime') ?: 3600);
        $now      = time();
        $jti      = bin2hex(random_bytes(16));

        $payload = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $lifetime,
            'sub' => (string) $user['id'],
            'iss' => HOST,
            'aud' => HOST,
            'jti' => $jti,
        ];

        $jwt      = \Firebase\JWT\JWT::encode($payload, SECRET_KEY, 'HS256');
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443;

        setcookie('auth_token', $jwt, [
            'expires'  => time() + $lifetime,
            'path'     => '/',
            'domain'   => parse_url(HOST, PHP_URL_HOST),
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $agora = (new \DateTimeImmutable())->setTimestamp($now);
        $_SESSION['user']['sessao_criada_em'] = $agora->format('Y-m-d H:i:s');
        $_SESSION['user']['sessao_expira_em'] = $agora->modify("+{$lifetime} seconds")->format('Y-m-d H:i:s');

        return $this->json($response, [
            'status'           => true,
            'msg'              => 'Seja bem vindo de volta!',
            'id'               => $user['id'],
            'sessao_expira_em' => $_SESSION['user']['sessao_expira_em'],
        ], 200);
    }
}
