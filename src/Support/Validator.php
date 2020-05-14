<?php

namespace Core\Support;

use Core\Exceptions\LangException;
use Core\Support\Time;

/**
 *  校验类
 */
class Validator
{
    const DATE_REG = '/^\d{4}(\-)\d{1,2}[0-2]\1\d{1,2}$/';
    const UTC_DATE_REG = '/^(?:[1-9]\d{3}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1\d|2[0-8])|(?:0[13-9]|1[0-2])-(?:29|30)|(?:0[13578]|1[02])-31)|(?:[1-9]\d(?:0[48]|[2468][048]|[13579][26])|(?:[2468][048]|[13579][26])00)-02-29)T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:Z|[+-][01]\d:[0-5]\d)$/';
    const EMAIL_REG = '/^[\.a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/';

    /**
     * 批量数据检测
     *
     * field:
     *     required 必填 true|false
     *     ignore 忽视数值 mixed|array
     *     type 类型 string
     *     default 默认值 mixed
     *
     * @return array
     * @throws LangException
     */
    public function validationData($data, $fields = [])
    {
        $safeData = [];

        foreach ($fields as $key => $field) {

            if (isset($field['required']) && $field['required']) {
                if (!isset($data[$key])) {
                    $this->_paramError($key . ' is required !');
                }

            }

            !isset($field['ignore']) && $field['ignore'] = [];

            if (isset($data[$key]) && !$this->isIgnore($field['ignore'], $data[$key])) { //值校验

                switch ($field['type']) {
                    case 'Number':
                        if (!is_numeric($data[$key])) {
                            $this->_paramError($key . ' must be number !');
                        }

                        if (!empty($field['min']) && +$data[$key] < $field['min']) {
                            $this->_paramError($key . ' value should >=' . $field['min']);
                        }

                        if (!empty($field['max']) && +$data[$key] > $field['max']) {
                            $this->_paramError($key . ' value should <=' . $field['max']);
                        }

                        $data[$key] = +$data[$key];
                        break;
                    case 'String':
                        $data[$key] = (string) $data[$key];
                        if (!empty($field['min']) && mb_strlen($data[$key], 'utf8') < $field['min']) {
                            $this->_paramError($key . ' value length should >=' . $field['min']);
                        }

                        if (!empty($field['max']) && mb_strlen($data[$key], 'utf8') > $field['max']) {
                            $this->_paramError($key . ' value length should <=' . $field['max']);
                        }

                        break;
                    case 'Enum':
                        if (!in_array($data[$key], $field['values'])) {
                            $this->_paramError($key . ' must in [' . implode(',', $field['values']) . ']');
                        }

                        break;
                    case 'Date':
                        if (!$this->isDate($data[$key])) {
                            $this->_paramError($key . ' value format should be date => yyyy-mm-dd !');
                        }
                        break;
                    case 'Array':
                        if (!$this->isArray($data[$key])) {
                            $this->_paramError($key . ' must be array !');
                        }
                        break;
                    case 'Email':
                        if (!$this->isEmail($data[$key])) {
                            $this->_paramError($key . ' error email format !');
                        }
                        break;
                    case 'UtcDate':
                        if (!$this->isUtcDate($data[$key])) {
                            $this->_paramError($key . ' must a ISO 8601 format date string!');
                        }
                        if (!empty($field['toTimezone']) || !empty($field['format'])) {
                            empty($field['format']) && $field['format'] = Time::FORMAT_ATOM;
                            empty($field['toTimezone']) && $field['time'] = 'Asia/Shanghai';
                            $data[$key] = Time::getInstance()->format($data[$key], $field['format'], $field['toTimezone']);
                        }
                        break;
                    default:
                        break;
                }
                $safeData[$key] = $data[$key];
            } else { //default
                if (isset($field['default'])) {
                    $safeData[$key] = $field['default'];
                }
            }

        }

        return $safeData;
    }

    public function isDate($value): bool
    {
        return preg_match(self::DATE_REG, $value) === 1;
    }

    public function isIgnore($ignore, $value): bool
    {
        if (is_array($ignore)) {
            return in_array($value, $ignore);
        } else {
            return $value === $ignore;
        }
    }

    public function isArray($value): bool
    {
        return \is_array($value);
    }

    public function isEmail($value): bool
    {
        return preg_match(self::EMAIL_REG, $value) === 1;
    }

    public function isUtcDate(string $value, array $field = []): bool
    {
        return preg_match(self::UTC_DATE_REG, $value) === 1;
    }

    protected function _paramError($msg = '')
    {
        throw new LangException(2, [$msg]);
    }

}
