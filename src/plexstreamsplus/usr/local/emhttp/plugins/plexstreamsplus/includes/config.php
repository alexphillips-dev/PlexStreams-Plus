<?php
    $plugin = "plexstreamsplus";
    $plg_path = "/boot/config/plugins/" . $plugin;
    $cfg_file    = "$plg_path/" . $plugin . ".cfg";
    $legacy_cfg_file = "/boot/config/plugins/plexstreams/plexstreams.cfg";

    if (file_exists($cfg_file)) {
        $cfg  = parse_ini_file($cfg_file);
    } else if (file_exists($legacy_cfg_file)) {
        // Migration fallback for installs that were previously "plexstreams".
        $cfg  = parse_ini_file($legacy_cfg_file);
    } else {
        $cfg = array();
    }
?>
