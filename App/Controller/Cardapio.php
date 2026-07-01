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
                // Cria novo cliente
                $conn->insert('customer', [
                    'nome_fantasia'   => $nome,
                    'sobrenome_razao' => '',
                    'cpf_cnpj'        => $cpf !== '' ? $cpf : '00000000000',
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

        if (empty($body['mesa_id']) || empty($body['itens']) || empty($body['pagamento'])) {
            return $this->json($response, [
                'sucesso' => false,
                'erro'    => 'Dados incompletos. Informe mesa_id, itens e pagamento.',
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
}
