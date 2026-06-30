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
             ativo,
             destaque_semana,
             destaque_ate'
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
                    'destaque'      => $p['destaque_semana'] && (empty($p['destaque_ate']) || $p['destaque_ate'] >= date('Y-m-d')),
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
        // { mesa: <id>, itens: [...], pagamento: '...', parcelas: N, intervalo: N, observacao: '...' }
        $novoBody = array_merge($body, [
            'mesa'      => $body['mesa_id'],
            'parcelas'  => isset($body['parcelas'])  ? (int) $body['parcelas']  : 1,
            'intervalo' => isset($body['intervalo']) ? (int) $body['intervalo'] : 0,
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
