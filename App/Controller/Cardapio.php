<?php

declare(strict_types=1);

namespace App\Controller;

final class Cardapio extends Base
{
    public function index($request, $response, $args)
    {
        // Pega o id direto da rota /cardapio/mesa/{id}
        $mesaNum    = isset($args['id']) ? (int) $args['id'] : null;
        $mesaValida = false;
        $mesaId     = null;

        if ($mesaNum !== null && $mesaNum >= 1) {
            $qb   = \App\Database\DB::select('id, numero, status')->from('mesa');
            $mesa = $qb
                ->where('numero = ' . $qb->createPositionalParameter($mesaNum, \Doctrine\DBAL\ParameterType::INTEGER))
                ->andWhere('ativo = true')
                ->fetchAssociative();

            if ($mesa && $mesa['status'] !== 'inativa') {
                $mesaValida = true;
                $mesaId     = (int) $mesa['id'];
            }
        }

        return $this->getTwig()
            ->render($response, $this->setView('cardapio'), [
                'mesa'       => $mesaValida ? $mesaNum : null,
                'mesa_id'    => $mesaId,
                'mesaValida' => $mesaValida,
                'nomeLocal'  => 'AllPratto',
            ])
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(200);
    }
    // GET /cardapio/itens → JSON
    public function getItens($request, $response)
    {
        try {

            $produtos = \App\Database\DB::select(
                'id,
             nome,
             preco_venda,
             grupo,
             descricao,
             imagem_url,
             nome_imagem,
             tempo_preparo,
             unidade,
             ativo'
            )
                ->from('product')
                ->where('ativo = true')
                ->andWhere('excluido = false')
                ->orderBy('grupo')
                ->addOrderBy('nome')
                ->fetchAllAssociative();

            $agrupado = [];

            foreach ($produtos as $p) {

                $categoria = !empty($p['grupo'])
                    ? $p['grupo']
                    : 'Outros';

                $agrupado[$categoria][] = [
                    'id'            => (int) $p['id'],
                    'nome'          => $p['nome'],
                    'descricao'     => $p['descricao'],
                    'grupo'         => $categoria,
                    'unidade'       => $p['unidade'],
                    'preco_venda'   => (float) $p['preco_venda'],
                    'imagem_url' => !empty($p['id']) ? '/product/get-imagem/' . $p['id'] : null,
                    'tempo_preparo' => $p['tempo_preparo'],
                    'destaque'      => false,
                ];
            }

            return $this->json($response, [
                'sucesso' => true,
                'dados'   => $agrupado,
            ], 200);
        } catch (\Exception $e) {

            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Erro ao buscar itens: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * POST /cardapio/identificar
     * Recebe { nome, cpf, email } do cliente no cardápio,
     * encontra ou cria o cliente e retorna { sucesso, id_cliente, nome }.
     */
    public function identificarCliente($request, $response)
    {
        $body  = $request->getParsedBody();
        $nome  = trim((string) ($body['nome']  ?? ''));
        $cpf   = preg_replace('/\D/', '', (string) ($body['cpf']   ?? ''));
        $email = trim((string) ($body['email'] ?? ''));

        if ($nome === '') {
            return $this->json($response, ['sucesso' => false, 'erro' => 'Nome é obrigatório.'], 400);
        }

        // Exigir CPF no cardápio: remove máscaras e valida tamanho
        if ($cpf === '' || strlen($cpf) !== 11) {
            return $this->json($response, ['sucesso' => false, 'erro' => 'CPF é obrigatório e deve conter 11 dígitos.'], 400);
        }

        try {
            $conn = \App\Database\DB::connection();

            // Tenta encontrar pelo CPF (campo principal de deduplicação)
            $cliente = null;
            if ($cpf !== '') {
                $qb = \App\Database\DB::select('id, nome_fantasia')->from('customer');
                $cliente = $qb->where('cpf_cnpj = :cpf')
                              ->setParameter('cpf', $cpf)
                              ->fetchAssociative();
            }

            if (!$cliente) {
                // Cria novo cliente (CPF agora obrigatório aqui)
                $conn->insert('customer', [
                    'nome_fantasia'   => $nome,
                    'sobrenome_razao' => '',
                    'cpf_cnpj'        => $cpf,
                    'ativo'           => true,
                ]);
                $idCliente = (int) $conn->lastInsertId();
                $nomeCliente = $nome;
            } else {
                $idCliente   = (int) $cliente['id'];
                $nomeCliente = $cliente['nome_fantasia'];
            }

            return $this->json($response, [
                'sucesso'    => true,
                'id_cliente' => $idCliente,
                'nome'       => $nomeCliente,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['sucesso' => false, 'erro' => $e->getMessage()], 500);
        }
    }

    public function salvarPedido($request, $response)
    {
        $body = $request->getParsedBody();

        if (empty($body['mesa_id']) || empty($body['itens']) || (empty($body['pagamento']) && empty($body['pagamentos']))) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Dados incompletos. Informe mesa_id, itens e pagamento (ou pagamentos).',
            ], 400);
        }

        // Repassa o body no formato que Pedido::insert espera
        // { mesa: <id>, itens: [...], pagamento: '...', parcelas: N, intervalo: N, observacao: '...', id_cliente: N }
        $novoBody = array_merge($body, [
            'mesa'       => $body['mesa_id'],
            'parcelas'   => isset($body['parcelas'])   ? (int) $body['parcelas']   : 1,
            'intervalo'  => isset($body['intervalo'])  ? (int) $body['intervalo']  : 0,
            'id_cliente' => isset($body['id_cliente']) && $body['id_cliente'] !== '' ? (int) $body['id_cliente'] : null,
        ]);

        $requestModificado = $request->withParsedBody($novoBody);
        // Se já existe um pedido aberto para a mesma mesa e mesmo cliente,
        // adicionamos os itens ao pedido existente em vez de criar um novo.
        $qbExist = \App\Database\DB::select('id, id_cliente, status')->from('"order"');
        $exist = $qbExist
            ->where('id_mesa = ' . $qbExist->createPositionalParameter($novoBody['mesa'], \Doctrine\DBAL\ParameterType::INTEGER))
            ->andWhere("status IN ('pendente','em_preparo')")
            ->orderBy('criado_em', 'DESC')
            ->setMaxResults(1)
            ->fetchAssociative();

        if ($exist && $novoBody['id_cliente'] !== null && (int) $exist['id_cliente'] === (int) $novoBody['id_cliente']) {
            // Adiciona itens um a um usando o método adicionarItem
            $added = 0;
            $errors = [];
            foreach ($novoBody['itens'] as $it) {
                $itemBody = [
                    'order_id'   => (int) $exist['id'],
                    'product_id' => isset($it['id']) && $it['id'] !== '' ? (int) $it['id'] : null,
                    'nome'       => $it['nome'] ?? '',
                    'preco'      => isset($it['preco']) ? (float) $it['preco'] : 0,
                    'quantidade' => isset($it['quantidade']) ? (int) $it['quantidade'] : 1,
                ];
                $reqItem = $request->withParsedBody($itemBody);
                $resItem = (new Pedido())->adicionarItem($reqItem, new \Slim\Psr7\Response());
                $bd = json_decode((string) $resItem->getBody(), true);
                if (($bd['status'] ?? $bd['sucesso'] ?? false) === true) {
                    $added++;
                } else {
                    $errors[] = $bd['msg'] ?? $bd['erro'] ?? 'Erro ao adicionar item';
                }
            }

            // Recupera total atualizado do pedido
            $qbTotal = \App\Database\DB::select('total')->from('"order"');
            $total = $qbTotal->where('id = ' . $qbTotal->createPositionalParameter((int) $exist['id'], \Doctrine\DBAL\ParameterType::INTEGER))->fetchOne();

            return $this->json($response, [
                'sucesso'   => empty($errors),
                'pedido_id' => (int) $exist['id'],
                'adicionados'=> $added,
                'erro'      => $errors ? implode('; ', $errors) : null,
                'total'     => (float) $total,
                'mensagem'  => empty($errors) ? 'Itens adicionados ao pedido existente.' : 'Alguns itens não puderam ser adicionados.',
            ], empty($errors) ? 200 : 207);
        }

        // Importante: passamos uma resposta NOVA e isolada aqui, e não a $response
        // recebida pela função. Se reaproveitássemos a mesma $response, o corpo
        // escrito pelo Pedido::insert() ficaria colado ao JSON que o salvarPedido()
        // escreve mais abaixo, gerando dois JSONs concatenados na mesma resposta
        // (o erro "Unexpected non-whitespace character after JSON" no front-end).
        $resultado = (new Pedido())->insert($requestModificado, new \Slim\Psr7\Response());

        // Pedido::insert retorna status/msg/id — adaptamos para o padrão do cardápio
        $dados = json_decode((string) $resultado->getBody(), true);

        if (!($dados['status'] ?? false)) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => $dados['msg'] ?? 'Erro ao salvar pedido.',
            ], $resultado->getStatusCode());
        }

        return $this->json($response, [
            'sucesso'   => true,
            'pedido_id' => $dados['id'],
            'mesa'      => $dados['mesa'],
            'total'     => $dados['total'],
            'mensagem'  => 'Pedido enviado para a cozinha!',
        ], 200);
    }

    // GET /cardapio/pedidos/mesa/{id} → JSON
    // Lista os pedidos já enviados à cozinha para a mesa, com status por
    // ITEM (via tabela kitchen), usado no painel "Meus Pedidos" do cliente.
    public function meusPedidos($request, $response, $args)
    {
        $mesaId = isset($args['id']) ? (int) $args['id'] : null;

        if (!$mesaId) {
            return $this->json($response, ['sucesso' => false, 'erro' => 'Informe a mesa.'], 400);
        }

        try {
            $pedidos = \App\Database\DB::select("
                o.id,
                o.status,
                o.total,
                o.observacao,
                to_char(o.criado_em, 'DD/MM/YYYY HH24:MI') AS criado_em,
                pt.titulo AS pagamento,
                c.nome_fantasia AS cliente_nome
            ")
                ->from('"order"', 'o')
                ->leftJoin('o', 'payment_terms', 'pt', 'pt.id = o.payment_terms_id')
                ->leftJoin('o', 'customer', 'c', 'c.id = o.id_cliente')
                ->where('o.id_mesa = :mesa')
                ->andWhere("o.status != 'pago'")
                ->setParameter('mesa', $mesaId, \Doctrine\DBAL\ParameterType::INTEGER)
                ->orderBy('o.criado_em', 'ASC')
                ->fetchAllAssociative();

            foreach ($pedidos as &$pedido) {
                $itens = \App\Database\DB::select("
                    oi.id AS order_item_id,
                    oi.nome,
                    oi.preco,
                    oi.quantidade,
                    oi.subtotal,
                    k.id AS kitchen_id,
                    COALESCE(k.status, 'Awaiting') AS status_cozinha
                ")
                    ->from('order_item', 'oi')
                    ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                    ->where('oi.order_id = :id')
                    ->setParameter('id', $pedido['id'], \Doctrine\DBAL\ParameterType::INTEGER)
                    ->orderBy('oi.id')
                    ->fetchAllAssociative();

                $pedido['itens'] = $itens;
            }
            unset($pedido);

            return $this->json($response, [
                'sucesso' => true,
                'pedidos' => $pedidos,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Erro ao buscar pedidos: ' . $e->getMessage(),
            ], 500);
        }
    }

    // POST /cardapio/pedido/cancelar-item → JSON
    // Cancela um item específico do pedido, mas só se ele ainda não estiver
    // "Ready" (pronto), "Delivered" (entregue) ou já "Cancelled".
    // Recalcula o total do pedido descontando o item cancelado.
    public function cancelarItem($request, $response)
    {
        $form        = $request->getParsedBody();
        $orderItemId = isset($form['order_item_id']) ? (int) $form['order_item_id'] : null;

        if (!$orderItemId) {
            return $this->json($response, ['sucesso' => false, 'erro' => 'Informe o item do pedido.'], 400);
        }

        try {
            $conn = \App\Database\DB::connection();

            $kitchen = \App\Database\DB::select('id, status, order_id')
                ->from('kitchen')
                ->where('order_item_id = :oid')
                ->setParameter('oid', $orderItemId, \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchAssociative();

            if (!$kitchen) {
                return $this->json($response, ['sucesso' => false, 'erro' => 'Item não encontrado.'], 404);
            }

            if (in_array($kitchen['status'], ['Ready', 'Delivered', 'Cancelled'], true)) {
                return $this->json($response, [
                    'sucesso' => false,
                    'erro'    => 'Este item já está pronto e não pode mais ser cancelado.',
                ], 409);
            }

            // Bloqueia cancelamento se o pedido estiver em status final (pronto/pago/entregue/cancelado)
            $qbOrder = \App\Database\DB::select('status')->from('"order"');
            $order = $qbOrder
                ->where('id = ' . $qbOrder->createPositionalParameter((int) $kitchen['order_id'], \Doctrine\DBAL\ParameterType::INTEGER))
                ->fetchAssociative();

            if ($order && in_array($order['status'], ['pronto', 'pago', 'entregue', 'cancelado'], true)) {
                return $this->json($response, [
                    'sucesso' => false,
                    'erro'    => 'Pedido com status "' . $order['status'] . '" não permite cancelamento de itens.',
                ], 409);
            }

            $conn->update('kitchen', [
                'status'     => 'Cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $kitchen['id']]);

            // Recalcula o total do pedido, excluindo itens cancelados
            $novoTotal = (float) \App\Database\DB::select("COALESCE(SUM(oi.subtotal), 0)")
                ->from('order_item', 'oi')
                ->leftJoin('oi', 'kitchen', 'k', 'k.order_item_id = oi.id')
                ->where('oi.order_id = :id')
                ->andWhere("COALESCE(k.status, 'Awaiting') != 'Cancelled'")
                ->setParameter('id', (int) $kitchen['order_id'], \Doctrine\DBAL\ParameterType::INTEGER)
                ->fetchOne();

            $conn->update('"order"', [
                'total'         => $novoTotal,
                'atualizado_em' => date('Y-m-d H:i:s'),
            ], ['id' => (int) $kitchen['order_id']]);

            return $this->json($response, [
                'sucesso'   => true,
                'mensagem'  => 'Item cancelado com sucesso!',
                'novoTotal' => $novoTotal,
            ], 200);
        } catch (\Exception $e) {
            return $this->json($response, ['sucesso' => false, 'erro' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}