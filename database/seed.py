"""
seed.py — Popula o banco PostgreSQL com dados fictícios usando Faker-BR.

Instale as dependências antes de rodar:
    pip install faker faker-br psycopg2-binary

Execute:
    python seed.py
"""

import random
import psycopg2
from faker import Faker
from faker_br import pt_BR   # Faker-BR com locale brasileiro

fake = Faker('pt_BR')

# ── Configuração do banco ────────────────────────────────────
DB = dict(
    host="localhost",
    port=5432,
    dbname="allpratto",
    user="postgres",
    password="sua_senha",
)

# ── Produtos fictícios ────────────────────────────────────────
PRODUTOS = [
    "Picanha Grelhada", "Frango à Milanesa", "Salmão ao Limão",
    "Massa Carbonara", "Risoto de Funghi", "Hambúrguer Artesanal",
    "Costela Assada", "Salada Caesar", "Tiramisu", "Petit Gâteau",
    "Suco de Laranja", "Refrigerante Lata", "Água Mineral",
    "Cerveja Artesanal", "Vinho Tinto Casa", "Camarão na Moranga",
    "Filé à Parmegiana", "Batata Rústica", "Sobremesa do Chef",
    "Buffet Executivo",
]

def seed():
    conn = psycopg2.connect(**DB)
    cur  = conn.cursor()

    # Limpa dados existentes (ordem respeita FK)
    cur.execute("TRUNCATE item_venda, venda, produto RESTART IDENTITY CASCADE;")

    # ── Insere produtos ──────────────────────────────────────
    for nome in PRODUTOS:
        cur.execute("INSERT INTO produto (nome) VALUES (%s);", (nome,))

    # ── Insere vendas (2 anos, todos os meses) ───────────────
    from datetime import date, timedelta
    import random

    for ano in [2024, 2025]:
        for mes in range(1, 13):
            # Entre 10 e 30 vendas por mês
            for _ in range(random.randint(10, 30)):
                dia = random.randint(1, 28)
                data_venda = date(ano, mes, dia)
                total = round(random.uniform(50, 800), 2)

                cur.execute(
                    "INSERT INTO venda (data_venda, total) VALUES (%s, %s) RETURNING id;",
                    (data_venda, total)
                )
                id_venda = cur.fetchone()[0]

                # Entre 1 e 5 itens por venda
                for _ in range(random.randint(1, 5)):
                    id_produto = random.randint(1, len(PRODUTOS))
                    quantidade = random.randint(1, 4)
                    valor      = round(random.uniform(15, 120), 2)
                    cur.execute(
                        "INSERT INTO item_venda (id_venda, id_produto, quantidade, valor) "
                        "VALUES (%s, %s, %s, %s);",
                        (id_venda, id_produto, quantidade, valor)
                    )

    conn.commit()
    cur.close()
    conn.close()
    print("✅ Seed concluído com sucesso!")

if __name__ == "__main__":
    seed()
