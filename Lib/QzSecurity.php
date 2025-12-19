<?php

namespace FacturaScripts\Plugins\DixTPV\Lib;

use FacturaScripts\Core\Tools;

/**
 * Gestiona la configuración y utilidades relacionadas con QZ Tray,
 * como la lectura del certificado público y la firma de peticiones.
 */
class QzSecurity
{
    private const DEFAULT_PUBLIC_CERT = 'MyFiles/Public/Certificate/digital-certificate.txt';
    private const DEFAULT_PRIVATE_KEY = 'MyFiles/Certificate/private-key.pem';
    private const DEFAULT_PRIVATE_KEY_PASS = 'MyFiles/Certificate/private-key.pass';

    /**
     * Devuelve la ruta (relativa o absoluta) configurada para el certificado público.
     */
    public static function getPublicCertSetting(): string
    {
        return Tools::settings('dixtpv', 'qz_public_cert_path', self::DEFAULT_PUBLIC_CERT);
    }

    /**
     * Devuelve la ruta (relativa o absoluta) configurada para la clave privada.
     */
    public static function getPrivateKeySetting(): string
    {
        return Tools::settings('dixtpv', 'qz_private_key_path', self::DEFAULT_PRIVATE_KEY);
    }

    /**
     * Devuelve la contraseña de la clave privada (si la hay).
     */
    public static function getPrivateKeyPassword(): string
    {
        $password = Tools::settings('dixtpv', 'qz_private_key_password', '');
        if ('' !== $password) {
            return $password;
        }

        $passSetting = Tools::settings('dixtpv', 'qz_private_key_pass_path', self::DEFAULT_PRIVATE_KEY_PASS);
        $passFile = self::resolvePath($passSetting);
        if (is_file($passFile) && is_readable($passFile)) {
            $content = file_get_contents($passFile);
            if ($content !== false) {
                $content = trim($content);
                if ('' !== $content) {
                    return $content;
                }
            }
        }

        $env = getenv('QZ_PRIVATE_KEY_PASSWORD');
        return false !== $env ? trim($env) : '';
    }

    /**
     * Indica si existen los ficheros necesarios para operar con QZ Tray.
     */
    public static function isFullyConfigured(): bool
    {
        return is_file(self::getPublicCertFsPath()) && is_file(self::getPrivateKeyFsPath());
    }

    /**
     * Devuelve el certificado público en texto plano o null si no se puede leer.
     */
    public static function readPublicCertificate(): ?string
    {
        $file = self::getPublicCertFsPath();
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $data = file_get_contents($file);
        return $data !== false ? $data : null;
    }

    /**
     * Firma el mensaje recibido con la clave privada configurada.
     */
    public static function signMessage(string $payload): ?string
    {
        if ('' === $payload) {
            return null;
        }

        $keyPath = self::getPrivateKeyFsPath();
        if (!is_file($keyPath) || !is_readable($keyPath)) {
            Tools::log()->warning('QZ Tray: clave privada no encontrada en ' . $keyPath);
            return null;
        }

        $keyContent = file_get_contents($keyPath);
        if (false === $keyContent) {
            Tools::log()->warning('QZ Tray: imposible leer la clave privada.');
            return null;
        }

        $password = self::getPrivateKeyPassword();
        $privateKey = openssl_pkey_get_private($keyContent, $password !== '' ? $password : null);
        if (false === $privateKey) {
            Tools::log()->warning('QZ Tray: no se pudo cargar la clave privada. ¿Contraseña incorrecta?');
            return null;
        }

        $signature = null;
        $result = openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA512);
        openssl_free_key($privateKey);

        if (!$result || null === $signature) {
            Tools::log()->warning('QZ Tray: error al firmar el mensaje.');
            return null;
        }

        return base64_encode($signature);
    }

    /**
     * Información que se expone al frontal para configurar QZ Tray.
     */
    public static function getFrontendConfig(): array
    {
        $certificateUrl = self::buildWebPath(self::getPublicCertSetting());
        $certificateContent = self::readPublicCertificate();

        return [
            'certificateUrl' => $certificateUrl,
            'certificateContent' => $certificateContent,
            'signUrl' => rtrim(FS_ROUTE, '/') . '/DixTPV?action=sign-qz-message',
            'configured' => self::isFullyConfigured(),
        ];
    }

    /**
     * Devuelve la ruta absoluta del certificado público.
     */
    private static function getPublicCertFsPath(): string
    {
        return self::resolvePath(self::getPublicCertSetting());
    }

    /**
     * Devuelve la ruta absoluta de la clave privada.
     */
    private static function getPrivateKeyFsPath(): string
    {
        return self::resolvePath(self::getPrivateKeySetting());
    }

    /**
     * Convierte una ruta relativa (respecto a FS_FOLDER) en absoluta.
     */
    private static function resolvePath(string $path): string
    {
        $normalised = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if (self::isAbsolutePath($normalised)) {
            return $normalised;
        }

        return rtrim(FS_FOLDER, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalised, DIRECTORY_SEPARATOR);
    }

    private static function isAbsolutePath(string $path): bool
    {
        return (
            str_starts_with($path, DIRECTORY_SEPARATOR) ||
            preg_match('/^[a-zA-Z]:\\\\/', $path) === 1
        );
    }

    /**
     * Construye la ruta web (para asset()) a partir de la configuración.
     */
    private static function buildWebPath(string $setting): ?string
    {
        $clean = str_replace('\\', '/', $setting);
        if (str_contains($clean, 'MyFiles/Public/')) {
            $parts = explode('MyFiles/Public/', $clean, 2);
            $relative = 'MyFiles/Public/' . ($parts[1] ?? '');
            return rtrim(FS_ROUTE, '/') . '/' . ltrim($relative, '/');
        }

        // Si no está dentro de MyFiles/Public no se puede servir directamente.
        return null;
    }
}
