# QZ Tray en el plugin DixTPV

El plugin `Plugins/DixTPV` ya fusiona el flujo moderno de tickets con la firma que exige QZ Tray. Esta guía resume dónde colocar los archivos y cómo activar la firma silenciosa.

---

## 1. Rutas por defecto

| Tipo | Ruta relativa | Ruta completa (este entorno) |
| --- | --- | --- |
| Certificado público | `MyFiles/Public/Certificate/digital-certificate.txt` | `c:\xamp\htdocs\facturascripts\MyFiles\Public\Certificate\digital-certificate.txt` |
| Clave pública opcional | `MyFiles/Public/Certificate/public-key.txt` | `c:\xamp\htdocs\facturascripts\MyFiles\Public\Certificate\public-key.txt` |
| Clave privada | `MyFiles/Certificate/private-key.pem` | `c:\xamp\htdocs\facturascripts\MyFiles\Certificate\private-key.pem` |
| Contraseña (opcional) | `MyFiles/Certificate/private-key.pass` | `c:\xamp\htdocs\facturascripts\MyFiles\Certificate\private-key.pass` |

> Si prefieres otras rutas, configura los ajustes `qz_public_cert_path`, `qz_private_key_path` y `qz_private_key_password` en **Admin → Herramientas → Ajustes → dixtpv**.

### Pasos

1. Crea las carpetas `MyFiles\Public\Certificate` y `MyFiles\Certificate` si todavía no existen.
2. Copia el certificado firmado por la CA en `digital-certificate.txt` (o usa `public-key.txt` y ajusta la ruta).
3. Copia la clave privada en `private-key.pem`.
4. Si la clave está protegida, guarda la contraseña en `private-key.pass` (una línea, sin espacios) o en el ajuste `qz_private_key_password`.
5. Ajusta los permisos de `MyFiles\Certificate` para que sólo el servicio web pueda leer esos ficheros.

---

## 2. Cómo se usa en FacturaScripts

- `Plugins/DixTPV/Lib/QzSecurity.php` resuelve rutas, lee el certificado y firma peticiones con `openssl_sign`. Usa primero los ajustes, luego el fichero `private-key.pass` y, en último término, la variable de entorno `QZ_PRIVATE_KEY_PASSWORD`.
- `Plugins/DixTPV/Controller/DixTPV.php` expone dos acciones internas:
  - `DixTPV?action=get-public-cert` devuelve el certificado al navegador.
  - `DixTPV?action=sign-qz-message` firma el “payload” que QZ Tray envía y responde en Base64.
- En el frontal, `Plugins/DixTPV/Assets/JS/dix-qz-security.js` inyecta la configuración, descarga el certificado y aplica `setCertificatePromise` / `setSignaturePromise` antes de conectar con QZ Tray.
- Si QZ Tray no está disponible, el flujo de `SendTicket` sigue mostrando la vista previa manual.

---

## 3. Checklist de puesta en marcha

1. **Copiar ficheros**  
   - `digital-certificate.txt` (o `public-key.txt`) y `private-key.pem`, más `private-key.pass` si la clave lleva contraseña.
2. **Importar en QZ Tray**  
   - QZ Tray → *Advanced* → *Certificates* → *Import* y selecciona el mismo archivo que sirve FacturaScripts. Reinicia QZ Tray después.
3. **Configurar contraseña (si aplica)**  
   - Guarda la contraseña en `private-key.pass`, en el ajuste `qz_private_key_password` o en la variable de entorno `QZ_PRIVATE_KEY_PASSWORD`.
4. **Probar firma local**  
   - Ejecuta `php scripts/test-qz-sign.php`. Debe devolver una cadena Base64 sin errores.
5. **Ensayo en el TPV**  
   - Abre el TPV y cobra una venta. QZ Tray no debería mostrar los cuadros “Untrusted website”. Si se desconecta QZ Tray, el fallback seguirá funcionando.

---

## 4. Diagnóstico rápido

- **Sigue pidiendo permisos** → El certificado no está importado en QZ Tray, la firma falla (revisa contraseña) o el fichero sirvió otro certificado.
- **El script de prueba falla** → Comprueba que `private-key.pem` y `private-key.pass` existen y la contraseña es correcta.  
- **Necesitas cambiar rutas** → Actualiza los ajustes o crea symlinks/copies en los paths por defecto.

Con esto, el plugin DixTPV imprime tickets vía QZ Tray sin diálogos ni confirmaciones adicionales.

