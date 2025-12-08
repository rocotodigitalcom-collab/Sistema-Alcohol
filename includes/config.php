<?php
/**
 * Configuración del Sistema - Alcohol Rocoto Digital
 * Configuraciones globales y constantes
 */

class Config {
    // Constantes de entorno
    const AMBIENTE = 'produccion'; // 'desarrollo' o 'produccion'
    const VERSION = '1.0.0';
    const NOMBRE_SISTEMA = 'Sistema Alcohol Rocoto Digital';
    
    /**
     * Obtener configuración completa del sistema
     * @return array
     */
    public static function obtenerConfiguracion() {
        return [
            'aplicacion' => [
                'nombre' => self::NOMBRE_SISTEMA,
                'version' => self::VERSION,
                'ambiente' => self::AMBIENTE,
                'url_base' => self::obtenerUrlBase()
            ],
            
            'database' => [
                'host' => 'localhost',
                'usuario' => 'tu_usuario_db',
                'password' => 'tu_password_db',
                'base_datos' => 'alcohol_rocoto_digital',
                'charset' => 'utf8mb4'
            ],
            
            'seguridad' => [
                'clave_secreta' => 'tu_clave_secreta_aqui',
                'token_expiracion' => 3600, // 1 hora en segundos
                'intentos_login' => 5
            ],
            
            'errores' => [
                'log_errores' => true,
                'mostrar_errores' => (self::AMBIENTE === 'desarrollo'),
                'archivo_log' => __DIR__ . '/../logs/errores.log'
            ]
        ];
    }
    
    /**
     * Obtener configuración de base de datos
     * @return array
     */
    public static function obtenerConfigDB() {
        $config = self::obtenerConfiguracion();
        return $config['database'];
    }
    
    /**
     * Obtener URL base de la aplicación
     * @return string
     */
    private static function obtenerUrlBase() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }
    
    /**
     * Verificar si está en modo desarrollo
     * @return bool
     */
    public static function esDesarrollo() {
        return self::AMBIENTE === 'desarrollo';
    }
    
    /**
     * Obtener configuración para mostrar errores
     * @return array
     */
    public static function obtenerConfigErrores() {
        if (self::esDesarrollo()) {
            return [
                'display_errors' => 1,
                'error_reporting' => E_ALL
            ];
        } else {
            return [
                'display_errors' => 0,
                'error_reporting' => E_ALL & ~E_NOTICE
            ];
        }
    }
}
?>