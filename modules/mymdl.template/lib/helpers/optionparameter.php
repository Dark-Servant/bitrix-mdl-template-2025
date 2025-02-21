<?
namespace MyMdl\Template\Helpers;

use \Bitrix\Main\Config\Option;

class OptionParameter
{
    const OPTION_NAME = 'installed';

    /**
     * При создании дополнительных модулей с наследованием от текущего
     * переменные $params и $moduleId надо обязательно объявить в
     * дочернем Options
     */
    protected $params = false;
    protected $name = self::OPTION_NAME;
    protected $moduleID;

    public function __construct(string $moduleID = null)
    {
        $this->moduleID = $moduleID ?? strtolower(implode('.', array_slice(preg_split('/\W+/', static::class), 0, -2)));
    }

    public function setName(string $value): static
    {
        $this->name = $value;
        return $this;
    }

    /**
     * Загрузка всех параметров модуля
     * 
     * @return array
     */
    protected function &loadParams(): array
    {
        if (!is_array($this->params)) {
            $data = Option::get(
                        $this->moduleID,
                        $this->name,
                        false, \CSite::GetDefSite()
                    );
            $this->params = $data ? json_decode($data, true) : [];
        }
        return $this->params;
    }

    /**
     * Получение всех параметров модуля
     * 
     * @return array
     */
    public function getParams(): array
    {
        return $this->loadParams();
    }

    /**
     * Сохранение всех параметров в модуле
     * 
     * @return void
     */
    public function save(): static
    {
        Option::set($this->moduleID, $this->name, json_encode($this->getParams()));
        return $this;
    }

    /**
     * Общий для всех статических get/set/add-методов
     * 
     * @param $method - название метода
     * @param $params - параметры метода
     * @return mixed
     */
    public function __call($method, $params)
    {
        if (!preg_match('/^([sg]et|add)(\w+)$/i', $method, $methodParts)) {
            return;
        }

        [, $actionName, $paramGroupName] = $methodParts;
        $paramCount = count($params);

        switch (strtolower($actionName)) {
            /**
             * Обработчик методов set<Название группы>. Метод полностью перезаписывает данные
             * конкретной группы
             */
            case 'set':
                if (!$paramCount) return null;
                
                // сохраняет последний переданный параметр
                return $resultValue = $this->loadParams()[$paramGroupName] = end($params);

            /**
             * Обработчик методов add<Название группы>. Метод добавляет данные к конкретной группы
             */
            case 'add':
                if (!$paramCount) return null;
                
                // берем первый параметр и запоминаем его как возвращаемое значение
                $resultValue = $firstParam = current($params);
                if (is_array($firstParam)) { // если этот параметр массив
                    // то только его данные дописываем к конкретной группе
                    $this->loadParams()[$paramGroupName] = array_replace($this->loadParams()[$paramGroupName], $firstParam);

                } elseif ($paramCount < 2) { // если первый параметр единственный переданный параметр
                    // то добавляем его к параметрам конкретной группы
                    $this->loadParams()[$paramGroupName][] = $firstParam;

                // если переданно несколько параметров, и первый либо целочисленное значение или непустая строка
                } elseif (is_numeric($firstParam) || (is_string($firstParam) && !empty($firstParam))) {
                    /**
                     * то в конкретной группе переписываем параметр с "ключом" равным значению первого параметра
                     * на значение последнего параметра
                     */
                    $resultValue = end($params);
                    $this->loadParams()[$paramGroupName][$firstParam] = $resultValue;

                } else {
                    return null;
                }
                return $resultValue;

            /**
             * По-умолчанию, обработчик методов get<Название группы>. Метод берет данные из конкретной группы
             */
            default:
                /**
                 * Получаем данные конкретной группы, и если не было переданно ни одного параметра,
                 * то возвращаем данные этой группы
                 */
                $group = $this->getParams()[$paramGroupName];
                if (!$paramCount) return $group;

                /**
                 * Если были указанны параметры, то берем те данные, которые хранятся под "ключами", названия
                 * которых указаны в параметрах
                 */
                $resultValue = [];
                foreach ($params as $paramName) {
                    if (!is_numeric($paramName) && (!is_string($paramName) || empty($paramName))) continue;

                    $resultValue[$paramName] = $group[$paramName];
                }

                /**
                 * Если параметров было переданно больше одно, то возвращаем весь собранный результат,
                 * иначе только значение первого параметра
                 */
                return $paramCount > 1 ? $resultValue : current($resultValue);
        }
    }
}