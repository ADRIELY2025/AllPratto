# Técnicas de Teste de Software

Repositório de apoio à aula **Técnicas de Teste de Software**, preparado para apresentação aos alunos do **Senac**.  
O projeto demonstra, de forma prática, a aplicação de testes automatizados em uma aplicação PHP moderna, utilizando ambiente containerizado, banco de dados relacional e pipeline de integração e deploy automatizado.

## Objetivo

Este repositório tem como finalidade servir como base didática para o estudo e demonstração de:

- fundamentos de testes de software
- testes automatizados em aplicações PHP
- organização de ambiente com Docker
- integração com banco de dados PostgreSQL
- execução de testes com Pest
- automação de validação e deploy com GitHub Actions

## Stack Tecnológica

- **PHP 8.5**
- **PostgreSQL 18.3**
- **Docker**
- **Pest PHP**
- **GitHub Actions**

## Estrutura do Projeto

Este ambiente foi planejado para fornecer uma base reprodutível e próxima de um cenário real de desenvolvimento, permitindo aos participantes executar a aplicação, rodar testes automatizados e compreender como práticas de qualidade de software podem ser integradas ao fluxo de entrega.

## Badges

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18.3-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?logo=docker&logoColor=white)](https://www.docker.com/)
[![Pest](https://img.shields.io/badge/Tests-Pest-7B68EE)](https://pestphp.com/)
[![GitHub Actions](https://img.shields.io/badge/CI%2FCD-GitHub_Actions-2088FF?logo=githubactions&logoColor=white)](https://github.com/features/actions)

## Como executar o projeto

### Subir o ambiente com Docker

```bash
docker compose up -d