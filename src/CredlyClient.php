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

use Toolbox;

/**
 * Responsável por consumir a API pública OBI da Credly.
 *
 * Decisões de integração documentadas em docs/ADR-002-credly-integration.md
 */
class CredlyClient
{
   /** @var \GuzzleHttp\Client */
   private $httpClient;

   private const ASSERTION_BASE_URI = 'https://api.credly.com/v1/obi/v2/';

   public function __construct() {
      $this->httpClient = Toolbox::getGuzzleClient([
         'base_uri' => self::ASSERTION_BASE_URI,
         'headers'  => [
            'Accept' => 'application/json',
         ],
      ]);
   }

   /**
    * Ponto de entrada público — dado o link do badge e o email do titular,
    * valida que o badge pertence ao usuário e retorna os dados consolidados.
    *
    * A validação é feita comparando o email informado com o hash SHA256
    * do recipient na assertion. A Credly hasheia o email para preservar
    * privacidade — a verificação é feita no sentido inverso: hasheia o
    * email informado e compara com o hash da assertion.
    *
    * Limitação: a validação depende do email que o usuário informa.
    * Se ele informar o email de outra pessoa, a validação passa.
    * Não há como verificar a titularidade sem autenticação na Credly.
    * Referência: docs/ADR-002-credly-integration.md
    *
    * @param string $badgeUrl URL pública do badge
    * @param string $email    Email usado pelo titular na Credly
    * @return array{
    *   name: string,
    *   issuer: string,
    *   description: string,
    *   image_url: string,
    *   tags: string[],
    *   external_id: string,
    *   issued_at: string,
    *   expires_at: string|null
    * }|null Retorna null se a URL for inválida, o badge não for acessível
    *        ou o email não corresponder ao titular do badge
    */
   public function fetchBadge(string $badgeUrl, string $email): ?array {
      if (!$badgeId = $this->extractBadgeId($badgeUrl)) {
         return null;
      }
      if (!$assertion = $this->fetchAssertion($badgeId)) {
         return null;
      }
      if (!$this->validateRecipient($assertion, $email)) {
         return null;
      }
      if (!$badgeClass = $this->fetchBadgeClass($assertion['badge'])) {
         return null;
      }

      return [
         'name'        => $badgeClass['name'],
         'issuer'      => $badgeClass['issuer']['name'],
         'description' => $badgeClass['description'],
         'image_url'   => $badgeClass['image']['id'],
         'tags'        => $badgeClass['tags'] ?? [],
         'external_id' => $badgeId,
         'issued_at'   => $assertion['issuedOn'],
         'expires_at'  => $assertion['expires'] ?? null,
      ];
   }

   /**
    * Valida que o email informado corresponde ao titular do badge.
    *
    * A Credly armazena o email como hash SHA256 com prefixo "sha256$".
    * Hasheamos o email informado e comparamos com o hash da assertion.
    *
    * @param array  $assertion Dados da assertion retornados pela API
    * @param string $email     Email informado pelo usuário
    * @return bool
    */
   private function validateRecipient(array $assertion, string $email): bool {
      $recipientHash = $assertion['recipient']['identity'] ?? '';

      // Remove o prefixo "sha256$" antes de comparar
      $storedHash = str_replace('sha256$', '', $recipientHash);
      $emailHash  = hash('sha256', $email);

      return hash_equals($storedHash, $emailHash);
   }

   /**
    * Extrai o badge_id de uma URL pública da Credly.
    *
    * Aceita os formatos:
    * - https://www.credly.com/badges/{id}
    * - https://www.credly.com/badges/{id}/public_url
    *
    * @param string $url URL pública do badge
    * @return string|null O badge_id em formato UUID, ou null se inválida
    */
   private function extractBadgeId(string $url): ?string {
      $pattern = '/credly\.com\/badges\/([a-f0-9\-]{36})/i';
      if (preg_match($pattern, $url, $matches)) {
         return $matches[1];
      }

      return null;
   }

   /**
    * Busca os dados da Badge Assertion — a emissão do badge para um usuário.
    * Retorna issuedOn, expires, recipient e a URL do BadgeClass.
    */
   private function fetchAssertion(string $badgeId): ?array {
      return $this->request("badge_assertions/{$badgeId}");
   }

   /**
    * Busca os dados do BadgeClass — o template da certificação.
    * Recebe a URL completa porque ela é retornada dinamicamente pela assertion.
    */
   private function fetchBadgeClass(string $badgeClassUrl): ?array {
      return $this->request($badgeClassUrl);
   }

   /**
    * Executa uma requisição GET e retorna o corpo como array.
    * Trata erros de conexão e respostas inesperadas de forma centralizada.
    *
    * @param string $uri URI relativa ou absoluta
    * @return array|null Retorna null em caso de erro
    */
   private function request(string $uri): ?array {
      try {
         $response = $this->httpClient->request('GET', $uri);
         $body     = (string) $response->getBody();
         $data     = json_decode($body, true);

         if (!is_array($data)) {
            Toolbox::logDebug("CredlyClient: resposta inesperada para {$uri}: {$body}");
            return null;
         }

         return $data;
      } catch (\GuzzleHttp\Exception\RequestException | \GuzzleHttp\Exception\ConnectException $e) {
         Toolbox::logDebug("CredlyClient: erro ao acessar {$uri}: " . $e->getMessage());
         return null;
      }
   }
}
