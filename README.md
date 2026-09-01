# Sistema original — preparación para Hostinger

Copia independiente del paquete softwareventaseinventarios2023.zip. No incluye stm-next ni utiliza Supabase. Se conservan las licencias originales; el repositorio privado no concede derechos adicionales sobre el software.

## Estado

Preparado para configuración y pruebas, **no certificado para producción**. Base inicial con 159 tablas y datos de demostración, sin inserciones de ventas, clientes ni productos. La cuenta admin no tiene una contraseña utilizable hasta configurarla. No se han ejecutado importaciones ni migraciones en MySQL de prueba.

## Instalación

1. Crear en Hostinger un sitio PHP con dominio temporal y una base MySQL nueva y vacía. Proteger el sitio temporalmente con contraseña desde el alojamiento y habilitar HTTPS.
2. Subir el contenido del repositorio a public_html, incluidos los archivos .htaccess, pero sin la carpeta .git. Conservar bibliotecas, imágenes y fuentes.
3. Copiar application/config/hosting.example.php como application/config/hosting.local.php SOLO en el servidor. Completar los datos de MySQL y una clave aleatoria privada de al menos 32 caracteres. No guardar ese archivo en GitHub ni compartirlo por chat.
4. Importar database/database.sql con phpMyAdmin únicamente en la base vacía. Contiene DROP TABLE: nunca ejecutarlo contra una instalación existente.
5. Configurar el administrador en phpMyAdmin, sustituyendo el marcador por una contraseña única. El original usa MD5; se conserva compatibilidad, pero esta limitación de seguridad heredada requiere revisión antes de producción:

```sql
UPDATE phppos_employees
SET password = MD5('REEMPLAZAR_POR_UNA_CONTRASENA_UNICA')
WHERE person_id = 1 AND username = 'admin';
```

6. Abrir el sitio protegido y completar las actualizaciones del instalador. Verificar PHP y extensiones con los requisitos del proveedor: la compatibilidad con tu plan todavía no está probada. Algunas migraciones utilizan el cliente mysql del servidor.
7. Cambiar empresa, moneda, idioma, impuestos, zona horaria, datos ficticios de sucursal y administrador. El nombre comercial está pendiente de elegir. No activar integraciones sin credenciales propias.
8. Probar producto, cliente, entrada de inventario, venta, devolución, cierre de caja y recibos; revisar logs antes de usar datos reales.

## Seguridad

- Mantener el repositorio privado y las licencias originales.
- No subir bases de producción ni archivos privados.
- No utilizar permisos 777; limitar escritura a las carpetas necesarias.
- Comprobar que el servidor devuelve 403 para database/database.sql y las carpetas internas.
- Realizar revisión de seguridad y compatibilidad antes de producción.

## Actualización experimental: control de lotes

La rama `inventory-lots-test` incorpora lotes de inventario, fechas de fabricación y vencimiento,
y asignación FIFO/FEFO. Antes de probarla en una instalación existente:

1. Crear una copia de seguridad completa de los archivos y la base de datos.
2. Actualizar los archivos conservando el `application/config/hosting.local.php` del servidor.
3. Ejecutar `application/migrations/20260831235900_inventory_lots.sql` una sola vez.
4. En instalaciones que ya tenían lotes, ejecutar también `application/migrations/20260901083000_inventory_lot_prices.sql` para agregar el precio de venta por lote.
5. Probar entradas, ventas, devoluciones, anulaciones, transferencias y artículos con variantes.

No activar el control por lotes en una instalación real hasta reconciliar las existencias actuales.
