<?
// Основные константы
define('MYMDL_TEMPLATE_MODULE_ID', basename(__DIR__));

// Данные о версии модуля
foreach ((require __DIR__ . '/install/version.php') as $key => $value) {
    define('MYMDL_TEMPLATE_' . $key, $value);
}