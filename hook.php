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

use GlpiPlugin\Certmap\DatabaseInstaller;

function plugin_certmap_install(): bool {
   return DatabaseInstaller::install();
}

function plugin_certmap_uninstall(): bool {
   return DatabaseInstaller::uninstall();
}
