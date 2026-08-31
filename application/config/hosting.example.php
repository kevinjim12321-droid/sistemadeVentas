<?php
defined('BASEPATH') OR exit('No direct script access allowed');
// Copiar como hosting.local.php SOLO en Hostinger. Nunca subir el archivo local a Git.
return array(
    'hostname' => 'localhost',
    'database' => '',
    'username' => '',
    'password' => '',
    // Generar en un entorno de confianza: bin2hex(random_bytes(32)).
    'encryption_key' => '',
);
