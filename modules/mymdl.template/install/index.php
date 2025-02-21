<?php
use Bitrix\Main\{
    Localization\Loc,
    Loader,
    Config\Option
};
use MyMdl\Template\EventHandles\Employment;

class mymdl_template extends CModule
{
    public $MODULE_ID;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;

    protected $nameSpaceValue;
    protected $subLocTitle;
    protected $optionParameterClass;
    protected $optionParameter = null;
    protected $definedContants;

    protected static $defaultSiteID;

    function __construct()
    {
        $this->initMainTitles()->initVersionTitles();
    }

    /**
     * Инициализирует название и описание модуля, а так же в процессе инициализации проходят
     * инициализацию другие переменные объекта класса, например, идентификатор модуля
     *
     * @return static
     */
    protected function initMainTitles(): static
    {
        $this->initModuleClassPath()->initOptionParameterClass();
        Loc::loadMessages($this->moduleClassPath . '/' . basename(__FILE__));

        $this->subLocTitle = strtoupper(static::class) . '_';
        $this->MODULE_NAME = Loc::getMessage($this->subLocTitle . 'MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage($this->subLocTitle . 'MODULE_DESCRIPTION');

        $this->PARTNER_NAME = Loc::getMessage($this->subLocTitle . 'PARTNER_NAME');
        return $this;
    }

    /**
     * Запоминает и возвращает настоящий путь к текущему классу
     * 
     * @return string
     */
    protected function initModuleClassPath(): static
    {
        $this->moduleClass = new \ReflectionClass(static::class);
        // не надо заменять на __DIR__, так как могут быть дополнительные модули $this->moduleClassPath
        $this->moduleClassPath = rtrim(preg_replace('/[^\/\\\\]+$/', '', $this->moduleClass->getFileName()), '\//');
        return $this;
    }

    /**
     * Запоминает и возвращает название класса, используемого для установки и сохранения
     * опций текущего модуля
     * 
     * @return string
     */
    protected function initOptionParameterClass(): static
    {
        $this->optionParameterClass = $this->initNameSpaceValue()->nameSpaceValue . '\\Helpers\\OptionParameter';
        return $this;
    }

    /**
     * Запоминает и возвращает название именного пространства для классов из
     * библиотеки модуля
     * 
     * @return string
     */
    protected function initNameSpaceValue(): static
    {
        $this->nameSpaceValue = preg_replace('/\.+/', '\\\\', ucwords($this->initModuleId()->MODULE_ID, '.'));
        return $this;
    }

    /**
     * Запоминает и возвращает код модуля, к которому относится текущий класс
     * 
     * @return string
     */
    protected function initModuleId(): static
    {
        $this->MODULE_ID = basename(dirname($this->moduleClassPath));
        return $this;
    }

    /**
     * Инициализирует переменные объекта класса, используя параметры из файла
     *      modules/<ID модуля>/install/version.php
     * и создает переменные объекта по правилу
     *      MODULE_<символьный код параметра> = <значение параметра>
     *
     * @return static
     */
    protected function initVersionTitles(): static
    {
        $versionFile = $this->moduleClassPath . '/version.php';
        if (!file_exists($versionFile)) {
            return $this;
        }

        $versionTitles = include $versionFile;
        if (empty($versionTitles) || !is_array($versionTitles)) {
            return $this;
        }

        foreach ($versionTitles as $versionParameterCode => $versionParameterValue) {
            $parameterCode = 'MODULE_' . strtoupper($versionParameterCode);
            $this->$parameterCode = $versionParameterValue;
        }
        return $this;
    }

    /**
     * Запоминает и возвращает кода сайта по-умолчанию
     * 
     * @return string
     */
    protected static function getDefaultSiteID()
    {
        if (self::$defaultSiteID) {
            return self::$defaultSiteID;
        }

        return self::$defaultSiteID = CSite::GetDefSite();
    }

    /**
     * По переданному имени возвращает значение константы текущего класса с учетом того, что эта константа
     * точно была (пере)объявлена в этом классе модуля. Конечно, получить значение константы класса можно
     * и через <название класса>::<название константы>, но такая запись не учитывает для дочерних классов,
     * что константа не была переобъявлена, тогда она может хранить ненужные старые данные, из-за чего требуется
     * ее переобъявлять, иначе дочерние модули начнуть устанавливать то же, что и родительские, а переобъявление
     * требует дополнительного внимания к каждой константе и дополнительных строк в коде дочерних модулей
     * 
     * @param string $constName - название константы
     * @return array
     */
    protected function getModuleConstantValue(string $constName)
    {
        $constant = $this->moduleClass->getReflectionConstant($constName);
        if (
            ($constant === false)
            || ($constant->getDeclaringClass()->getName() != static::class)
        ) return [];

        return $constant->getValue();
    }

    /**
     * Подключает модуль и сохраняет созданные им константы
     * 
     * @return void
     */
    protected function initDefinedContants()
    {
        /**
         * array_keys нужен, так как в array_filter функция isset дает
         * лишнии результаты
         */
        $this->definedContants = array_keys(get_defined_constants());

        Loader::IncludeModule($this->MODULE_ID);
        $this->definedContants = array_filter(
            get_defined_constants(),
            function($key) {
                return !in_array($key, $this->definedContants);
            }, ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Выполняется основные операции по установке модуля
     * 
     * @return void
     */
    protected function runInstallMethods()
    {
    }

    /**
     * Проверяет у модуля наличие класса Employment в своем подпространстве имен EventHandles,
     * а так же наличие у него метода, название которого передано в параметре $methodName.
     * В случае успеха вызывает метод у своего Employment
     * 
     * @param string $methodName - название метода, который должен выступать как обработчик события
     * @return void
     */
    protected function checkAndRunModuleEvent(string $methodName)
    {
        $moduleEmployment = $this->nameSpaceValue . '\\EventHandles\\Employment';
        if (!class_exists($moduleEmployment) || !method_exists($moduleEmployment, $methodName))
            return;

        $moduleEmployment::$methodName();
    }

    /**
     * Функция, вызываемая при установке модуля
     *
     * @param bool $stopAfterInstall - указывает модулю остановить после
     * своей установки весь процесс установки
     * 
     * @return void
     */
    public function DoInstall(bool $stopAfterInstall = true) 
    {
        global $APPLICATION;
        RegisterModule($this->MODULE_ID);
        $this->initDefinedContants();

        try {
            if (!$this->getOptionParameter()) {
                throw new Exception(Loc::getMessage('ERROR_NO_OPTION_CLASS', ['#CLASS#' => $this->optionParameterClass]));
            }

            Employment::setBussy();
            $this->checkAndRunModuleEvent('onBeforeModuleInstallationMethods');
            $this->runInstallMethods();
            $this->getOptionParameter()->setConstants(array_keys($this->definedContants));
            $this->getOptionParameter()->setInstallShortData([
                'INSTALL_DATE' => date('Y-m-d H:i:s'),
                'VERSION' => $this->MODULE_VERSION,
                'VERSION_DATE' => $this->MODULE_VERSION_DATE,
            ]);
            $this->getOptionParameter()->save();
            $this->checkAndRunModuleEvent('onAfterModuleInstallationMethods');
            Employment::setFree();
            if ($stopAfterInstall) {
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage($this->subLocTitle . 'MODULE_WAS_INSTALLED'),
                    $this->moduleClassPath . '/step1.php'
                );
            }

        } catch (Exception $error) {
            $this->removeAll();
            $APPLICATION->ThrowException($error->getMessage());
            Employment::setFree();
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage($this->subLocTitle . 'MODULE_NOT_INSTALLED'),
                $this->moduleClassPath . '/error.php'
            );
        }
    }

    /**
     * Выполняется основные операции по удалению модуля
     * 
     * @return void
     */
    protected function runRemoveMethods()
    {
    }

    /**
     * Основной метод, очищающий систему от данных, созданных им
     * при установке
     * 
     * @return void
     */
    protected function removeAll()
    {
        if ($this->getOptionParameter()) {
            $this->definedContants = array_fill_keys($this->getOptionParameter()->getConstants() ?? [], '');
            array_walk($this->definedContants, function(&$value, $key) { $value = constant($key); });
            $this->runRemoveMethods();
        }
        UnRegisterModule($this->MODULE_ID); // удаляем модуль
    }

    protected function getOptionParameter()
    {
        if (!class_exists($this->optionParameterClass)) {
            return false;
        }

        if ($this->optionParameter) {
            return $this->optionParameter;
        }

        $optionParameterClass = $this->optionParameterClass;
        return $this->optionParameter = new $optionParameterClass($this->MODULE_ID);
    }

    /**
     * Функция, вызываемая при удалении модуля
     *
     * @param bool $stopAfterDeath - указывает модулю остановить после
     * своего удаления весь процесс удаления
     * 
     * @return void
     */
    public function DoUninstall(bool $stopAfterDeath = true) 
    {
        global $APPLICATION;
        Loader::IncludeModule($this->MODULE_ID);
        Employment::setBussy();
        $this->checkAndRunModuleEvent('onBeforeModuleRemovingMethods');
        $this->removeAll();
        Option::delete($this->MODULE_ID);
        $this->checkAndRunModuleEvent('onAfterModuleRemovingMethods');
        Employment::setFree();
        if ($stopAfterDeath)
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage($this->subLocTitle . 'MODULE_WAS_DELETED'),
                $this->moduleClassPath . '/unstep1.php'
            );
    }

}
