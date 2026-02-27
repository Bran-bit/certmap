# ADR-001 — Classe DatabaseInstaller

**Data:** 2026-02-27

## Contexto

O `hook.php` é o ponto de entrada obrigatório do GLPI para instalação e desinstalação de plugins, o que inclui a criação e exclusão de tabelas utilizadas pelo plugin. Com essa lógica diretamente no hook, o arquivo ficaria longo e o foco de lidar com todas as dependências da instalação e desinstalação seria perdido.

## Decisão

Criar a classe `GlpiPlugin\Certmap\DatabaseInstaller` em `src/DatabaseInstaller.php` para encapsular toda a lógica de criação e remoção de tabelas. O `hook.php` passa a ser apenas um ponto de entrada que delega para essa classe.

A classe foi implementada como **estática** porque não guarda estado — é um agrupamento de funções utilitárias relacionadas à estrutura do banco.

## Estrutura

```
hook.php                         → ponto de entrada, delega para DatabaseInstaller
src/DatabaseInstaller.php        → lógica de criação e remoção de tabelas
```

**Métodos públicos:**
- `DatabaseInstaller::install()` — orquestra a instalação, chama `createTables()`
- `DatabaseInstaller::uninstall()` — orquestra a desinstalação, chama `dropTables()`

**Métodos privados:**
- `getSchema()` — fonte de verdade do schema, retorna `array<string, string>` com nome da tabela como chave e query `CREATE TABLE` como valor
- `createTables()` — itera o schema e cria as tabelas que não existem
- `dropTables()` — itera o schema em ordem inversa e remove as tabelas

## Schema declarativo

O schema é definido como queries SQL diretas, armazenadas em um array em que as chaves são o nome da tabela, e os valores correspondem às queries de criação da respectiva tabela. 
O nome da tabela como chave do array permite que e `dropTables()` resgate essa informação para o comando DROP, enquanto `createTables()` utiliza do valor dessa chave para ter a query de criação. Desse modo, os dois métodos compartilham da mesma fonte de dados, garantindo que todas as tabelas criadas pelo plugin também sejam excluídas quando necessário.

## Campos de auditoria

Todas as tabelas incluem `date_creation` e `date_mod`. Esses campos são populados automaticamente pelo `CommonDBTM` do GLPI quando detecta que as colunas existem na tabela — não é necessário setar esses valores manualmente no código da aplicação.

Para evitar repetição, esses campos são extraídos em variáveis `$auditFields` e `$auditIndexes` dentro de `getSchema()` e interpolados em cada query.

Referência: `CommonDBTM.php` linhas 806, 858, 1371, 1750.

## Ordem de remoção

`dropTables()` usa `array_reverse(array_keys(getSchema()))` para remover as tabelas na ordem inversa da criação. Embora o GLPI não use foreign key constraints reais no banco — a integridade referencial é garantida pelo código da aplicação — a ordem inversa é mantida como boa prática.
