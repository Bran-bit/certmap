# ADR-002 — Integração com a API da Credly

**Data:** 2026-02-27

## Contexto

O plugin precisa importar certificações de técnicos sem exigir cadastro manual. A Credly é a plataforma usada pela maioria das certificadoras (AWS, Cisco, Google, etc.) para emitir badges digitais.

## Endpoints disponíveis

A Credly implementa o padrão **Open Badges v2 (OBI)** da IMS Global. Os endpoints OBI são públicos e não exigem autenticação.

### 1. Badge Assertion
```
GET https://api.credly.com/v1/obi/v2/badge_assertions/{badge_id}
```

Retorna dados da emissão do badge para um usuário específico:
- `issuedOn` — data de obtenção
- `expires` — data de expiração (ausente quando o badge não expira — não é `null`, simplesmente não aparece no JSON)
- `badge` — URL completa do BadgeClass correspondente
- `recipient.identity` — email do titular em hash SHA256 com prefixo `sha256$`

### 2. Badge Class
```
GET {url retornada pelo campo badge da assertion}
```

A URL é retornada dinamicamente pela assertion — não deve ser hardcoded. Retorna:
- `name` — nome da certificação
- `issuer.name` — nome da organização emissora
- `description` — descrição da certificação
- `image.id` — URL da imagem do badge
- `tags` — competências associadas à certificação

## Fluxo de importação

1. Técnico informa a URL pública do badge e o email usado na Credly
2. Plugin extrai o `badge_id` da URL via regex
3. Busca a Badge Assertion
4. Valida que o email informado corresponde ao titular do badge
5. Busca o BadgeClass usando a URL retornada pela assertion
6. Consolida os dados e retorna estrutura pronta para salvar no banco
7. As `tags` do BadgeClass podem popular `certification_competencies` automaticamente

## Validação de titularidade

A Credly armazena o email do titular como hash SHA256 com prefixo `sha256$` para preservar privacidade. A validação é feita no sentido inverso:

1. Remove o prefixo `sha256$` do hash da assertion
2. Aplica `hash('sha256', $email)` no email informado pelo usuário
3. Compara os dois hashes com `hash_equals()` — função resistente a timing attacks

**Limitação:** a validação depende do email que o usuário informa. Se ele informar o email de outra pessoa, a validação passa. Não há como verificar titularidade sem autenticação na Credly, pois a API não expõe o email em claro. Essa limitação é inerente à API e aceita como risco residual.

## Como testar

O script abaixo carrega o ambiente do GLPI e chama o `CredlyClient` diretamente. A ordem dos includes é obrigatória — inverter quebra o carregamento das classes.

```bash
docker compose exec glpi bash -c 'cat > /tmp/test_credly.php << '"'"'EOF'"'"'
<?php
require "/var/www/glpi/vendor/autoload.php";   // classes do GLPI e dependências
include "/var/www/glpi/inc/includes.php";       // inicializa banco, sessão e configurações
require "/var/www/glpi/plugins/certmap/vendor/autoload.php"; // classes do plugin

$client = new GlpiPlugin\Certmap\CredlyClient();
$result = $client->fetchBadge(
    "https://www.credly.com/badges/{badge_id}/public_url",
    "email-usado-na-credly@exemplo.com"
);

var_dump($result);
EOF'

docker compose exec glpi php /tmp/test_credly.php
```

Resultado esperado com badge válido e email correto:
```
array(8) {
  ["name"]        => string "AWS Academy Graduate - Cloud Foundations - Training Badge"
  ["issuer"]      => string "Amazon Web Services Training and Certification"
  ["description"] => string "Earners of this badge have taken the AWS Academy Cloud Foundations course."
  ["image_url"]   => string "https://images.credly.com/images/..."
  ["tags"]        => array(5) { ... }
  ["external_id"] => string "daa8ce50-bb9a-46aa-b1c2-ab78d5c38701"
  ["issued_at"]   => string "2025-07-23T14:11:46.000Z"
  ["expires_at"]  => NULL
}
```

Retorna `null` se a URL for inválida, o badge for privado, ou o email não corresponder ao titular.

## Restrições e limitações

**Sem autenticação:** os endpoints OBI são públicos. Não é necessário API key.

**Badges privados:** retornam erro. O plugin deve informar o técnico nesse caso.

**Sem catálogo:** não existe endpoint público para listar todas as certificações. O catálogo cresce organicamente conforme os técnicos registram seus badges.

**Rate limiting:** não documentado pela Credly para os endpoints OBI. O `CredlyClient` captura exceções do Guzzle e registra em log via `Toolbox::logDebug`.

**Expiração:** o campo `expires` está ausente quando o badge não expira.

## API privada descartada

Durante a investigação foi encontrado o endpoint interno:
```
GET https://www.credly.com/api/v1/users/{user_id}/badges
```

Retorna todos os badges de um usuário com dados mais ricos, mas exige o `user_id` em formato UUID — não acessível publicamente a partir do username sem autenticação. Descartado por exigir que o técnico descubra manualmente seu UUID inspecionando requisições de rede.

## Alternativas consideradas

**Cadastro manual** — descartado como fluxo principal, mantido como fallback para certificações fora da Credly.

**Endpoint privado com user_id** — descartado por má experiência do usuário.

**Scraping do HTML** — descartado por ser frágil a mudanças de layout.

**Usar email do GLPI em vez de pedir ao usuário** — descartado porque o email no GLPI pode ser diferente do usado na Credly.