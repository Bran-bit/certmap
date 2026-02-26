# CertMap — GLPI Plugin

Plugin para gestão de certificações, competências e elegibilidade técnica integrado ao GLPI.

## Problema

O GLPI gerencia diversos ativos de TI, mas não possui suporte nativo para certificações técnicas — que expiram, definem quais problemas um técnico pode resolver e são fundamentais para decisões de atribuição de chamados. Sem esse controle, equipes não sabem com clareza quem está habilitado para atender cada tipo de demanda, quais certificações estão vencidas e quais lacunas de capacitação precisam ser endereçadas.

## Solução

O CertMap implementa um subsistema de gestão de competências integrado aos módulos nativos do GLPI (Users, Groups, Tickets), permitindo:

- Registrar e validar certificações obtidas por técnicos
- Mapear competências derivadas dessas certificações em uma hierarquia (ex: `Cloud → AWS Cloud → AWS Solutions Architect → Associate`)
- Definir quais competências são necessárias por categoria de chamado e por grupo técnico
- Sugerir automaticamente técnicos elegíveis na abertura de chamados, com fallback hierárquico de competências
- Rastrear competências adquiridas por histórico de chamados resolvidos, com aprovação de gestor
- Gerar relatórios de lacunas de competências e alertas de certificações próximas do vencimento

## Design

As certificações são tratadas como **evidência de competência**, não como o centro do sistema. Uma competência pode ser adquirida por certificação, por histórico de chamados resolvidos (com aprovação de gestor) ou por atribuição manual.

## Modelo de Dados

| Tabela | Descrição |
|--------|-----------|
| `glpi_plugin_certmap_competencies` | Hierarquia de competências técnicas |
| `glpi_plugin_certmap_certifications` | Catálogo de certificações (AWS, Cisco, Google, etc.) |
| `glpi_plugin_certmap_certification_competencies` | Relação entre certificações e competências que elas conferem |
| `glpi_plugin_certmap_user_certifications` | Certificações obtidas por cada técnico |
| `glpi_plugin_certmap_user_competencies` | Competências de cada técnico e sua origem |
| `glpi_plugin_certmap_category_requirements` | Competências exigidas por categoria de chamado |
| `glpi_plugin_certmap_group_requirements` | Competências exigidas por grupo técnico |
| `glpi_plugin_certmap_ticket_progress` | Progresso de aquisição de competência via tickets |

## Requisitos

- GLPI >= 11.0
- PHP >= 8.1
- MySQL >= 8.0

## Instalação

```bash
# Clone o repositório na pasta de plugins do GLPI
git clone https://github.com/Bran-bit/certmap /var/www/glpi/plugins/certmap

# Instale as dependências
cd /var/www/glpi/plugins/certmap
composer install
```

Após clonar, acesse **Configuração → Plugins** no GLPI e clique em **Instalar** e depois **Ativar** no CertMap.

## Desenvolvimento

O ambiente de desenvolvimento usa Docker. Com o Docker e o Git instalados:

```bash
git clone https://github.com/Bran-bit/certmap
cd certmap
composer install
```

Configure o `docker-compose.yml` com um bind mount apontando para a pasta do plugin:

```yaml
volumes:
  - ./certmap:/var/www/glpi/plugins/certmap
```

Acesse o GLPI em `http://localhost:8080`.

### Padrão de código

O projeto segue o padrão de codificação do GLPI, verificado pelo `phpcs`. O VSCode já está configurado via `.vscode/settings.json` para aplicar o padrão automaticamente ao salvar.

Para verificar manualmente:

```bash
vendor/bin/phpcs --standard=vendor/glpi-project/coding-standard/GlpiStandard .
```

Para corrigir automaticamente:

```bash
vendor/bin/phpcbf --standard=vendor/glpi-project/coding-standard/GlpiStandard .
```

## Roadmap

- [x] Levantamento de requisitos
- [x] Modelagem do banco de dados
- [x] Configuração do ambiente de desenvolvimento
- [ ] Estrutura mínima do plugin (setup.php, hook.php)
- [ ] Migrations das tabelas
- [ ] CRUD de competências e certificações
- [ ] Registro de certificações por técnico
- [ ] Sugestão de técnicos em chamados
- [ ] Integração com Credly API
- [ ] Alertas de vencimento
- [ ] Relatórios de lacunas
- [ ] Dashboard de capacidade técnica

## Licença

GPLv3
