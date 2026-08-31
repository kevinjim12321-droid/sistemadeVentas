<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$hostingFile = __DIR__ . '/hosting.local.php';
if (!is_file($hostingFile)) {
    http_response_code(503);
    exit('Instalacion pendiente: configura application/config/hosting.local.php.');
}
$hosting = require $hostingFile;
if (!is_array($hosting) || empty($hosting['encryption_key']) || strlen($hosting['encryption_key']) < 32 || empty($hosting['database']) || empty($hosting['username'])) {
    http_response_code(503);
    exit('La configuracion privada de hosting esta incompleta.');
}
