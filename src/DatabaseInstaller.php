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

use DBConnection;

/**
 * Responsável pela criação e remoção das tabelas do plugin no banco de dados.
 *
 * Chamado pelo hook.php durante a instalação e desinstalação do plugin.
 * O schema é definido como queries SQL diretas — o nome da tabela é a chave
 * do array, permitindo que createTables() e dropTables() compartilhem a
 * mesma fonte de verdade sem duplicação.
 *
 * Decisões de design documentadas em docs/ADR-001-database-installer.md
 *
 * Classe estática pois não guarda estado — é um agrupamento de funções
 * utilitárias relacionadas à estrutura do banco.
 */
class DatabaseInstaller
{
   /**
    * Ponto de entrada para instalação.
    * Orquestra todos os passos necessários para instalar o plugin.
    */
   public static function install(): bool {
      static::createTables();
      return true;
   }

   /**
    * Ponto de entrada para desinstalação.
    * Orquestra todos os passos necessários para remover o plugin.
    */
   public static function uninstall(): bool {
      static::dropTables();
      return true;
   }

   /**
    * Retorna o schema completo do plugin como array associativo.
    * Chave: nome da tabela. Valor: query CREATE TABLE completa.
    *
    * Esta é a única fonte de verdade para a estrutura do banco.
    * Detalhes de domínio de cada tabela em docs/ADR-001-database-installer.md
    *
    * Campos de auditoria (date_creation e date_mod):
    * Presentes em todas as tabelas e populados automaticamente pelo
    * CommonDBTM do GLPI — não é necessário setar esses valores manualmente.
    * Referência: CommonDBTM.php linhas 806, 858, 1371, 1750
    *
    * Chaves estrangeiras usam DEFAULT 0 seguindo a convenção do GLPI,
    * onde 0 representa ausência de vínculo (IDs válidos começam em 1).
    *
    * @return array<string, string>
    */
   private static function getSchema(): array {
      $sign         = DBConnection::getDefaultPrimaryKeySignOption();
      $charset      = DBConnection::getDefaultCharset();
      $collation    = DBConnection::getDefaultCollation();
      $tableOptions = "ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC";

      $auditFields  = "
               `date_creation` timestamp NULL DEFAULT NULL,
               `date_mod`      timestamp NULL DEFAULT NULL,";

      $auditIndexes = "
               KEY `date_mod` (`date_mod`),
               KEY `date_creation` (`date_creation`)";

      return [
         'glpi_plugin_certmap_competencies' => "
            CREATE TABLE `glpi_plugin_certmap_competencies` (
               `id`               int {$sign} NOT NULL AUTO_INCREMENT,
               `name`             varchar(255) NOT NULL,
               `parent_id`        int {$sign} NULL DEFAULT NULL,
               `ticket_threshold` int {$sign} NULL DEFAULT NULL,
               `comment`          text NULL,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `parent_id` (`parent_id`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_certifications' => "
            CREATE TABLE `glpi_plugin_certmap_certifications` (
               `id`          int {$sign} NOT NULL AUTO_INCREMENT,
               `name`        varchar(255) NOT NULL,
               `issuer`      varchar(255) NOT NULL,
               `external_id` varchar(255) NULL DEFAULT NULL,
               `comment`     text NULL,
               {$auditFields}
               PRIMARY KEY (`id`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_certification_competencies' => "
            CREATE TABLE `glpi_plugin_certmap_certification_competencies` (
               `id`                               int {$sign} NOT NULL AUTO_INCREMENT,
               `plugin_certmap_certifications_id` int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_competencies_id`   int {$sign} NOT NULL DEFAULT 0,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `plugin_certmap_certifications_id` (`plugin_certmap_certifications_id`),
               KEY `plugin_certmap_competencies_id` (`plugin_certmap_competencies_id`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_user_certifications' => "
            CREATE TABLE `glpi_plugin_certmap_user_certifications` (
               `id`                               int {$sign} NOT NULL AUTO_INCREMENT,
               `users_id`                         int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_certifications_id` int {$sign} NOT NULL DEFAULT 0,
               `obtained_at`                      timestamp NULL DEFAULT NULL,
               `expires_at`                       timestamp NULL DEFAULT NULL,
               `status`                           tinyint NOT NULL DEFAULT 1,
               `credential_url`                   varchar(255) NULL DEFAULT NULL,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `users_id` (`users_id`),
               KEY `plugin_certmap_certifications_id` (`plugin_certmap_certifications_id`),
               KEY `status` (`status`),
               KEY `expires_at` (`expires_at`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_user_competencies' => "
            CREATE TABLE `glpi_plugin_certmap_user_competencies` (
               `id`                             int {$sign} NOT NULL AUTO_INCREMENT,
               `users_id`                       int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_competencies_id` int {$sign} NOT NULL DEFAULT 0,
               `source`                         tinyint NOT NULL DEFAULT 1,
               `source_id`                      int {$sign} NULL DEFAULT NULL,
               `acquired_at`                    timestamp NULL DEFAULT NULL,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `users_id` (`users_id`),
               KEY `plugin_certmap_competencies_id` (`plugin_certmap_competencies_id`),
               KEY `source` (`source`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_category_requirements' => "
            CREATE TABLE `glpi_plugin_certmap_category_requirements` (
               `id`                             int {$sign} NOT NULL AUTO_INCREMENT,
               `itilcategories_id`              int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_competencies_id` int {$sign} NOT NULL DEFAULT 0,
               `is_mandatory`                   tinyint NOT NULL DEFAULT 1,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `itilcategories_id` (`itilcategories_id`),
               KEY `plugin_certmap_competencies_id` (`plugin_certmap_competencies_id`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_group_requirements' => "
            CREATE TABLE `glpi_plugin_certmap_group_requirements` (
               `id`                             int {$sign} NOT NULL AUTO_INCREMENT,
               `groups_id`                      int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_competencies_id` int {$sign} NOT NULL DEFAULT 0,
               `is_mandatory`                   tinyint NOT NULL DEFAULT 1,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `groups_id` (`groups_id`),
               KEY `plugin_certmap_competencies_id` (`plugin_certmap_competencies_id`),
               {$auditIndexes}
            ) {$tableOptions}",

         'glpi_plugin_certmap_ticket_progress' => "
            CREATE TABLE `glpi_plugin_certmap_ticket_progress` (
               `id`                             int {$sign} NOT NULL AUTO_INCREMENT,
               `users_id`                       int {$sign} NOT NULL DEFAULT 0,
               `plugin_certmap_competencies_id` int {$sign} NOT NULL DEFAULT 0,
               `ticket_count`                   int {$sign} NOT NULL DEFAULT 0,
               `status`                         tinyint NOT NULL DEFAULT 1,
               {$auditFields}
               PRIMARY KEY (`id`),
               KEY `users_id` (`users_id`),
               KEY `plugin_certmap_competencies_id` (`plugin_certmap_competencies_id`),
               KEY `status` (`status`),
               {$auditIndexes}
            ) {$tableOptions}",
      ];
   }

   /**
    * Cria todas as tabelas do plugin que ainda não existem no banco.
    * A verificação é feita em PHP antes de executar a query.
    */
   private static function createTables(): void {
      /** @var \DBmysql $DB */
      global $DB;

      foreach (static::getSchema() as $tableName => $createQuery) {
         if (!$DB->tableExists($tableName)) {
            $DB->doQuery($createQuery);
         }
      }
   }

   /**
    * Remove todas as tabelas do plugin na ordem inversa da criação.
    * A ordem inversa é mantida como boa prática mesmo o GLPI não usando
    * foreign key constraints reais — a integridade é garantida pela aplicação.
    */
   private static function dropTables(): void {
      /** @var \DBmysql $DB */
      global $DB;

      foreach (array_reverse(array_keys(static::getSchema())) as $tableName) {
         $DB->doQuery("DROP TABLE IF EXISTS `{$tableName}`");
      }
   }
}
