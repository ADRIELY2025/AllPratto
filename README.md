<div align="center">

# 🍽️ AllPratto

**Cardápio Digital com Painel Administrativo**

Sistema completo para restaurantes, lanchonetes, cafeterias e qualquer estabelecimento alimentício — do carrinho humilde ao restaurante premium. O cliente escaneia o QR Code da mesa, faz o pedido direto pelo celular e o pedido já aparece na cozinha automaticamente.

</div>

---

## ✨ O que é o AllPratto

O AllPratto é uma plataforma **self-hosted** (roda no seu próprio servidor) dividida em duas grandes frentes:

**Cardápio público (acesso via QR Code)**
Qualquer pessoa com o link da mesa consegue acessar sem login. Ela vê o cardápio, adiciona itens ao carrinho, escolhe a forma de pagamento (PIX, Crédito, Débito ou Dinheiro) e finaliza o pedido. Ao confirmar, o sistema grava automaticamente: pedido, itens, venda, parcelas e movimenta o estoque — tudo em uma única transação.

**Painel administrativo (acesso restrito por login)**
Exclusivo para administradores e operadores do estabelecimento. Aqui ficam o cadastro de produtos, mesas, clientes, fornecedores, condições de pagamento, controle de estoque, relatórios de vendas e o monitor da cozinha em tempo real.

---

## 🗺️ Fluxo do pedido

```
Cliente escaneia QR Code da mesa
        │
        ▼
Cardápio Digital (público, sem login)
  ├── Visualiza produtos por categoria
  ├── Adiciona itens ao carrinho
  ├── Escolhe forma de pagamento
  │     ├── PIX / Dinheiro → 1 parcela
  │     └── Crédito / Débito → define parcelas + intervalo em dias
  └── Clica em "Fazer Pedido"
        │
        ▼
Backend grava em uma única transação:
  1. order          → pedido na mesa
  2. order_item     → itens do pedido
  3. kitchen        → cozinha (via trigger do banco)
  4. payment_terms  → forma de pagamento
  5. installment    → linha por parcela
  6. sale           → venda vinculada (PRE_VENDA)
  7. item_sale      → itens da venda
  8. purchase       → saída de estoque
  9. item_purchase  → itens da saída
 10. installment_sale_purchase → vínculo parcela ↔ venda ↔ compra
 11. mesa.status   → marcada como "ocupada"
        │
        ▼
Monitor da Cozinha atualiza em tempo real
```

---

## 🏗️ Stack de tecnologia

### Backend

| Tecnologia | Versão | Função |
|---|---|---|
| **PHP** | 8.5.6 | Linguagem principal |
| **Slim Framework** | 4.15 | Micro-framework HTTP / roteamento |
| **Twig** | via slim/twig-view 3.4 | Templates HTML (painel admin) |
| **Doctrine DBAL** | 4.4 | Query Builder e conexão com o banco |
| **Doctrine Migrations** | 3.9 | Versionamento do schema do banco |
| **endroid/qr-code** | 6.1 | Geração do QR Code de cada mesa |
| **firebase/php-jwt** | 7.0 | Autenticação via JWT |
| **league/oauth2-google** | 5.0 | Login social com Google |
| **vlucas/phpdotenv** | 5.6 | Leitura das variáveis de ambiente |

### Frontend

| Tecnologia | Versão | Função |
|---|---|---|
| **Vite** | 8.0.10 | Bundler e dev server dos assets |
| **Bootstrap** | 5.3.8 | Layout e componentes do painel |
| **DataTables** | 2.3.8 | Tabelas paginadas e ordenáveis |
| **ECharts** | 6.1.0 | Gráficos do dashboard |
| **SweetAlert2** | 11.26.24 | Modais e alertas bonitos |
| **Select2** | 4.1.0 | Selects com busca |
| **Flatpickr** | 4.6.13 | Date picker |
| **Font Awesome** | 7.2.0 | Ícones |
| **jQuery** | 3.7.1 | DOM e AJAX do painel admin |

### Infraestrutura (Docker)

| Container | Imagem | Função |
|---|---|---|
| **app_traefik** | traefik:v3.6.14 | Reverse proxy + TLS (HTTPS automático) |
| **app_nginx** | nginx:1.31.0-alpine3.23 | Servidor web / entrega dos assets |
| **app_php** | php:8.5.6-fpm-alpine3.23 (custom) | PHP-FPM — executa a aplicação |
| **app_postgres** | postgres:18.4-alpine3.23 | Banco de dados relacional |
| **app_redis** | redis:8.6-alpine3.23 | Cache e sessões |

### Qualidade de código (dev)

| Ferramenta | Versão | Função |
|---|---|---|
| **Pest** | 4.0 | Testes unitários e de integração |
| **PHPStan** | 2.0 | Análise estática |
| **Laravel Pint** | 1.0 | Formatação de código (PSR-12) |
| **Mockery** | 1.6 | Mocks para testes |
| **FakerPHP** | 1.24 | Geração de dados falsos nos testes |

---

## 📁 Estrutura do projeto

```
AllPratto/
├── App/
│   ├── Controller/         # Controllers (Mesa, Pedido, Cardapio, Sale...)
│   ├── Database/
│   │   ├── Migration/      # Migrations versionadas do banco
│   │   ├── Connection.php  # Configuração PDO/Doctrine
│   │   └── DB.php          # Query Builder helper
│   ├── Helpers/            # Vite helper, Settings
│   ├── Middleware/         # Auth, API guard
│   ├── Routes/             # routes.php — todas as rotas
│   ├── Trait/              # Response, Template, DatabaseValueNormalizer
│   └── View/
│       └── pages/          # Templates Twig do painel admin
│           └── cardapio/   # Template do cardápio público
├── docker/
│   ├── nginx/default.conf  # Configuração do Nginx
│   ├── php/Dockerfile      # Build multi-stage (Node → assets, PHP → app)
│   ├── php/php.ini         # php.ini customizado
│   ├── postgres/init.sql   # Script inicial do banco
│   ├── redis/              # Persistência do Redis
│   └── traefik/            # Configuração do reverse proxy + TLS
├── public/                 # Raiz pública (index.php + assets compilados)
├── resources/
│   ├── css/                # CSS por página (incluindo cardapio.css)
│   └── js/
│       ├── components/     # Módulos reutilizáveis (requests.js, etc.)
│       └── pages/          # JS por página (cardapio.js, mesa.js, etc.)
├── storage/
│   └── qrcode/             # QR Codes gerados (um subdir por mesa)
├── Tests/                  # Testes Pest
├── composer.json
├── package.json
├── vite.config.js
└── docker-compose.yml
```

---

## 🔐 Controle de acesso

O sistema tem **dois níveis de acesso completamente separados**:

**Cardápio público** — rotas `/cardapio/*` — sem autenticação
- `/cardapio/mesa/{numero}` — página do cardápio (acessada pelo QR Code)
- `/cardapio/itens` — API que retorna os produtos ativos
- `/cardapio/pedido` — API que recebe e grava o pedido

**Painel administrativo** — todas as outras rotas — exige login
- Protegido pelo `Middleware::web()` e `Middleware::api()`
- Login local (usuário + senha) ou OAuth2 com Google
- Sessão armazenada no Redis

> O QR Code gerado no cadastro de cada mesa aponta diretamente para `/cardapio/mesa/{numero}`. Quem escaneou acessa apenas o cardápio — nunca o painel.

---

## 🚀 Instalação e execução

### Pré-requisitos

- [Docker](https://docs.docker.com/get-docker/) + Docker Compose v2
- [Node.js](https://nodejs.org/) >= 22.12.0 (somente para desenvolvimento local do frontend)
- [Composer](https://getcomposer.org/) >= 2.9 (somente para desenvolvimento sem Docker)

---

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/AllPratto.git
cd AllPratto
```

---

### 2. Configure o ambiente

Copie o arquivo de exemplo e preencha as variáveis:

```bash
cp .env.example .env
```

Variáveis essenciais no `.env`:

```env
# Aplicação
APP_DOMAIN=localhost
APP_ENV=development
APP_DEBUG=true
TZ=America/Sao_Paulo
PROTOCOL=https

# Banco de dados (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_NAME=allpratto
DB_USER=allpratto
DB_PASSWORD=sua_senha_aqui

# Redis (sessões e cache)
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha_redis
REDIS_DATABASE=1
REDIS_PREFIX=SESS:

# Google OAuth (opcional)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://localhost/authentication/google
```

---

### 3. Suba os containers

```bash
# Sobe tudo em background
docker compose up -d

# Acompanha os logs enquanto sobe (útil na primeira vez — o composer install demora ~90s)
docker compose logs -f php
```

Na primeira vez, o container PHP executa automaticamente:
```
composer install --no-interaction --prefer-dist --optimize-autoloader
```

---

### 4. Rode as migrations (banco de dados)

```bash
docker compose exec php php vendor/bin/doctrine-migrations migrate --no-interaction
```

Isso cria todas as tabelas, views, triggers e funções do banco.

---

### 5. Acesse o sistema

| URL | O quê |
|---|---|
| `https://localhost` | Painel administrativo (login) |
| `https://localhost/cardapio/mesa/1` | Cardápio da mesa 1 (público) |

> O certificado TLS em desenvolvimento é autoassinado pelo Traefik. O navegador vai alertar na primeira vez — clique em "avançar assim mesmo".

---

## 🖥️ Desenvolvimento local (frontend com hot reload)

Para editar CSS/JS e ver as mudanças instantaneamente sem rebuild Docker:

```bash
# Instala dependências Node
npm install

# Inicia o Vite em modo dev (hot reload)
npm run dev
```

O Vite sobe em `http://localhost:5173`. O helper `Vite.php` detecta o arquivo `/public/hot` e injeta os assets diretamente do dev server.

---

## 📦 Build dos assets para produção

```bash
# Compila e minifica os assets com hash de cache busting
npm run build
```

Os arquivos gerados vão para `public/assets/`. O Dockerfile já executa esse passo automaticamente no build da imagem.

---

## 🧪 Testes e qualidade

```bash
# Roda os testes
docker compose exec php composer test

# Testes com relatório de cobertura (mínimo 80%)
docker compose exec php composer test:cov

# Relatório de cobertura em HTML
docker compose exec php composer test:html
# → abre storage/coverage/index.html

# Análise estática com PHPStan
docker compose exec php composer analyse

# Formata o código (PSR-12 via Pint)
docker compose exec php composer format

# Verifica formatação sem alterar
docker compose exec php composer format:check

# Pipeline completo (format:check + analyse + test:cov)
docker compose exec php composer ci
```

---

## 🗄️ Comandos úteis do banco

```bash
# Cria uma nova migration
docker compose exec php php vendor/bin/doctrine-migrations generate

# Status das migrations
docker compose exec php php vendor/bin/doctrine-migrations status

# Reverte a última migration
docker compose exec php php vendor/bin/doctrine-migrations migrate prev --no-interaction

# Acessa o psql direto
docker compose exec postgres psql -U allpratto -d allpratto
```

---

## 🐳 Comandos Docker úteis

```bash
# Sobe os containers
docker compose up -d

# Para os containers (mantém os dados)
docker compose down

# Para e apaga volumes (APAGA O BANCO!)
docker compose down -v

# Rebuild da imagem PHP (após mudar o Dockerfile ou php.ini)
docker compose build php --no-cache
docker compose up -d php

# Ver logs em tempo real
docker compose logs -f
docker compose logs -f php
docker compose logs -f postgres

# Status e uso de recursos
docker compose ps
docker stats

# Acessa o shell do PHP
docker compose exec php sh

# Reinicia um serviço específico
docker compose restart nginx
```

---

## 📱 Como funciona o QR Code das mesas

1. No painel admin, acesse **Lista de mesas → Cadastrar mesa**
2. Preencha o número da mesa, capacidade e status
3. Ao salvar, o sistema gera automaticamente o arquivo `storage/qrcode/{id}/mesa_{id}.png`
4. O QR Code aponta para `https://seu-dominio/cardapio/mesa/{numero}`
5. Imprima o QR Code e cole na mesa física
6. O cliente escaneia → acessa o cardápio → faz o pedido

---

## 🏭 Deploy em produção

Para produção, o Traefik pode emitir certificados TLS reais via Let's Encrypt (Cloudflare DNS). No `.env` de produção:

```env
APP_DOMAIN=restaurante.com.br
APP_ENV=production
APP_DEBUG=false
CF_DNS_API_TOKEN=seu_token_cloudflare
```

E descomente no `docker-compose.yml` ou num `docker-compose.prod.yml`:
```yaml
- "traefik.http.routers.app.tls.certresolver=letsencrypt"
```

---

## 📄 Licença

MIT — veja o arquivo `LICENSE` para detalhes.

---

<div align="center">
Feito para funcionar em qualquer lugar — do food truck ao restaurante cinco estrelas. 🍕
</div>
