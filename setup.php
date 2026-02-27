<?php
define('CERTMAP_VERSION', '1.0.0');

function plugin_certmap_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '11.0', 'lt')) {
       echo "Este plugin requer GLPI >= 11.0";
       return false;
   }
    return true;
}

function plugin_init_certmap() {

}

function plugin_version_certmap() {
    return [
        'name' => 'certmap',
        'version' => CERTMAP_VERSION,
        'author' => 'Brandon Oliveira',
        'license' => 'GLPv3',
        'requirements' => [
        'glpi' => [
                'min' => '11.0.0'
                    ]
        ]
        ];
}
