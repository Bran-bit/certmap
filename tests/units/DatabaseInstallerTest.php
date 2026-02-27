<?php

use GlpiPlugin\Certmap\DatabaseInstaller;

class DatabaseInstallerTest extends TestCase {
   public function testInstallCreatesTable(): void {
       DatabaseInstaller::install();

      foreach (array_keys(DatabaseInstaller::getSchema()) as $tableName) {
         $this->assertTrue($DB->tableExists($tableName));
      }
   }

   public function testUninstallDropsTables(): void {
      global $DB;

      DatabaseInstaller::install();
      DatabaseInstaller::uninstall();

      foreach (array_keys(DatabaseInstaller::getSchema()) as $tableName) {
          $this->assertFalse($DB->tableExists($tableName));
      }
   }
}
