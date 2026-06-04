-- ============================================================
--  AllPratto — Views PostgreSQL + Seed com dados fictícios
--  Gerado com dados no estilo Faker-BR (em Python, veja seed.py)
-- ============================================================

-- ── Tabelas base (crie se ainda não existirem) ───────────────

CREATE TABLE IF NOT EXISTS produto (
    id   SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS venda (
    id          SERIAL PRIMARY KEY,
    data_venda  DATE NOT NULL,
    total       NUMERIC(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS item_venda (
    id          SERIAL PRIMARY KEY,
    id_venda    INT REFERENCES venda(id),
    id_produto  INT REFERENCES produto(id),
    quantidade  INT NOT NULL,
    valor       NUMERIC(10,2) NOT NULL
);

-- ============================================================
--  VIEW 1 — Vendas totais por mês e ano  (gráfico de barras)
-- ============================================================
CREATE OR REPLACE VIEW vw_vendas_por_mes AS
SELECT
    EXTRACT(YEAR  FROM data_venda)::INT AS ano,
    EXTRACT(MONTH FROM data_venda)::INT AS mes,
    TO_CHAR(data_venda, 'Mon/YYYY')     AS label,
    SUM(total)::NUMERIC(12,2)           AS total_vendas
FROM venda
GROUP BY ano, mes, label
ORDER BY ano, mes;

-- ============================================================
--  VIEW 2 — Curva ABC dos produtos mais vendidos  (pizza)
-- ============================================================
CREATE OR REPLACE VIEW vw_curva_abc AS
WITH ranked AS (
    SELECT
        p.nome                                    AS produto,
        SUM(iv.quantidade * iv.valor)             AS receita,
        SUM(SUM(iv.quantidade * iv.valor)) OVER() AS total_geral
    FROM item_venda iv
    JOIN produto p ON p.id = iv.id_produto
    GROUP BY p.nome
),
acumulado AS (
    SELECT
        produto,
        receita,
        total_geral,
        SUM(receita) OVER (ORDER BY receita DESC) AS acum,
        ROUND(receita / total_geral * 100, 2)     AS pct
    FROM ranked
)
SELECT
    produto,
    receita,
    pct,
    CASE
        WHEN acum / total_geral <= 0.70 THEN 'A'
        WHEN acum / total_geral <= 0.90 THEN 'B'
        ELSE                                 'C'
    END AS classe_abc
FROM acumulado
ORDER BY receita DESC;
