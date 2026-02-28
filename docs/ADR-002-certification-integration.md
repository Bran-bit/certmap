# ADR-002 — Integração com plataformas de certificação

**Data:** 2026-02-27

## Contexto

O plugin precisa importar certificações de técnicos sem exigir cadastro manual. A Credly é a plataforma mais usada pelas certificadoras (AWS, Cisco, Google, etc.), mas outras plataformas como Accredible e Microsoft Learn também emitem certificações relevantes.

## Decisão: interface BadgeClientInterface

Foi criada a interface `BadgeClientInterface` para padronizar o contrato entre o código do plugin e qualquer plataforma de certificação. O código que salva no banco não precisa saber de onde vieram os dados — só precisa receber o contrato cumprido.

```php
interface BadgeClientInterface
{
    public function fetchBadge(string $url, string $email): ?array;
}
```

O `CredlyClient` é a primeira implementação. Quando uma nova plataforma for necessária, basta criar uma nova classe que implemente a interface — sem tocar no código existente.

## O que sabemos vs o que falta

A interface foi criada com base **apenas na API da Credly**. Antes de implementar outras plataformas, é necessário validar seus endpoints e verificar se o contrato é compatível.

**O que sabemos (validado com a Credly):**
- `name` — toda plataforma séria expõe o nome da certificação
- `issuer` — toda plataforma expõe o emissor
- `external_id` — toda plataforma tem um identificador único
- `issued_at` — toda plataforma registra a data de emissão

**O que é incerto para outras plataformas:**
- `description` — pode não existir ou ter nome diferente
- `image_url` — nem toda plataforma tem badge visual
- `tags` — é específico do padrão Open Badges; outras plataformas podem chamar de `skills`, `competencies` ou não ter
- Formato das datas — a Credly usa ISO 8601; outras podem usar formatos diferentes
- Validação de titularidade — a Credly usa hash SHA256 do email; outras podem não ter nenhum mecanismo público de verificação

**Plataformas a investigar antes de implementar:**
- Accredible — usada por Google Cloud e outras certificadoras
- Microsoft Learn — plataforma própria da Microsoft para certificações Azure, etc.

Para cada nova plataforma: testar os endpoints disponíveis, verificar adesão ao padrão Open Badges v2, documentar as diferenças, e avaliar se o contrato da interface precisa ser ajustado.

## Integração com a Credly

### Endpoints utilizados

**Badge Assertion** — emissão do badge para um usuário:
```
GET https://api.credly.com/v1/obi/v2/badge_assertions/{badge_id}
```
Retorna: `issuedOn`, `expires` (ausente quando não expira), `badge` (URL do BadgeClass), `recipient.identity` (email em hash SHA256 com prefixo `sha256$`)

**Badge Class** — template da certificação:
```
GET {url retornada pelo campo badge da assertion}
```
Retorna: `name`, `issuer.name`, `description`, `image.id`, `tags`

### Fluxo de importação

1. Técnico informa a URL pública do badge e o email usado na Credly
2. Plugin extrai o `badge_id` da URL via regex
3. Busca a Badge Assertion
4. Valida titularidade pelo email
5. Busca o BadgeClass usando a URL retornada pela assertion
6. Retorna estrutura padronizada pela interface

### Validação de titularidade

A Credly armazena o email do titular como hash SHA256 com prefixo `sha256$`. A validação:

1. Remove o prefixo `sha256$` do hash da assertion
2. Aplica `hash('sha256', $email)` no email informado
3. Compara com `hash_equals()` — resistente a timing attacks

**Limitação:** se o usuário informar o email de outra pessoa, a validação passa. Não há como verificar titularidade sem autenticação. Risco residual aceito.

### Restrições

- Endpoints OBI são públicos — sem API key
- Badges privados retornam erro
- Sem catálogo público de certificações — o banco cresce organicamente
- Rate limiting não documentado — erros são capturados e logados via `Toolbox::logDebug`

## Como testar

```bash
docker compose exec glpi bash -c 'cat > /tmp/test_credly.php << '"'"'EOF'"'"'
<?php
require "/var/www/glpi/vendor/autoload.php";
include "/var/www/glpi/inc/includes.php";
require "/var/www/glpi/plugins/certmap/vendor/autoload.php";

$client = new GlpiPlugin\Certmap\CredlyClient();
$result = $client->fetchBadge(
    "https://www.credly.com/badges/{badge_id}/public_url",
    "email-usado-na-credly@exemplo.com"
);

var_dump($result);
EOF'

docker compose exec glpi php /tmp/test_credly.php
```

A ordem dos includes é obrigatória:
1. `vendor/autoload.php` do GLPI — registra `Toolbox`, `Guzzle` e demais dependências
2. `inc/includes.php` — inicializa banco, sessão e configurações do GLPI
3. `vendor/autoload.php` do plugin — registra as classes do certmap

## Alternativas descartadas

**Endpoint privado `api/v1/users/{user_id}/badges`** — retorna dados mais ricos mas exige UUID do usuário, não acessível publicamente a partir do username.

**Scraping do HTML** — frágil a mudanças de layout.

**Usar email do GLPI** — o email no GLPI pode ser diferente do usado na Credly.

**Cadastro manual** — mantido como fallback para certificações fora de plataformas suportadas.