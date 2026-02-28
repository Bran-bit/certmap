<?php

/**
 * -------------------------------------------------------------------------
 * CertMap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of CertMap.
 *
 * CertMap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * @copyright Copyright (C) 2025 by Brandon Oliveira Simões
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/Bran-bit/certmap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Certmap;

/**
 * Contrato que todo cliente de plataforma de certificação deve cumprir.
 *
 * Qualquer classe que implemente esta interface pode ser usada no lugar
 * de outra sem que o código consumidor precise mudar — desde que o array
 * de retorno siga a estrutura definida abaixo.
 *
 * A interface foi criada após validar a API da Credly (Open Badges v2).
 * Os campos obrigatórios refletem o mínimo que qualquer plataforma
 * de certificação séria expõe. Os campos opcionais podem ser null
 * quando a plataforma não os fornece.
 *
 * Antes de implementar para uma nova plataforma, valide os endpoints
 * disponíveis e documente as diferenças em um novo ADR.
 * Referência: docs/ADR-002-credly-integration.md
 *
 * Estrutura do array de retorno:
 * @return array{
 *   name: string,         — obrigatório: nome da certificação
 *   issuer: string,       — obrigatório: nome da organização emissora
 *   external_id: string,  — obrigatório: identificador único na plataforma de origem
 *   issued_at: string,    — obrigatório: data de emissão (ISO 8601)
 *   description: string|null, — opcional: descrição da certificação
 *   image_url: string|null,   — opcional: URL da imagem do badge
 *   tags: string[],           — opcional: competências associadas (array vazio se não houver)
 *   expires_at: string|null   — opcional: data de expiração (ISO 8601), null se não expira
 * }|null
 */
interface BadgeClientInterface
{
   /**
    * Busca e valida as informações de uma certificação a partir de uma URL pública.
    *
    * Implementações devem validar que a certificação pertence ao titular
    * do email informado antes de retornar os dados.
    *
    * @param string $url   URL pública da certificação na plataforma
    * @param string $email Email do titular usado na plataforma
    * @return array|null   Retorna null se a URL for inválida, a certificação
    *                      não for acessível, ou o email não corresponder ao titular
    */
   public function fetchBadge(string $url, string $email): ?array;
}
