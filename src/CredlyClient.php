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
 * Implementação do BadgeClientInterface para a plataforma Credly.
 * Consome a API pública OBI (Open Badges v2) da Credly.
 *
 * Decisões de integração documentadas em docs/ADR-002-credly-integration.md
 */
class CredlyClient implements BadgeClientInterface
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
    * Busca e valida as informações de um badge da Credly.
    *
    * Extrai o badge_id da URL, valida a titularidade via hash SHA256
    * do email, e consolida os dados dos dois endpoints OBI necessários.
    *
    * Limitação: a validação depende do email que o usuário informa.
    * Se ele informar o email de outra pessoa, a validação passa.
    * Não há como verificar titularidade sem autenticação na Credly.
    * Referência: docs/ADR-002-credly-integration.md
    *
    * @param string $url   URL pública do badge (ex: https://www.credly.com/badges/{id}/public_url)
    * @param string $email Email usado pelo titular na Credly
    * @return array|null
    */
   public function fetchBadge(string $url, string $email): ?array {
      if (!$badgeId = $this->extractBadgeId($url)) {
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
         'external_id' => $badgeId,
         'issued_at'   => $assertion['issuedOn'],
         'description' => $badgeClass['description'],
         'image_url'   => $badgeClass['image']['id'],
         'tags'        => $badgeClass['tags'] ?? [],
         'expires_at'  => $assertion['expires'] ?? null,
      ];
   }

   /**
    * Valida que o email informado corresponde ao titular do badge.
    *
    * A Credly armazena o email como hash SHA256 com prefixo "sha256$".
    * Hasheamos o email informado e comparamos com o hash da assertion.
    * hash_equals() é usado por ser resistente a timing attacks.
    */
   private function validateRecipient(array $assertion, string $email): bool {
      $recipientHash = $assertion['recipient']['identity'] ?? '';
      $storedHash    = str_replace('sha256$', '', $recipientHash);
      $emailHash     = hash('sha256', $email);

      return hash_equals($storedHash, $emailHash);
   }

   /**
    * Extrai o badge_id de uma URL pública da Credly.
    * Aceita com ou sem sufixo /public_url.
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
