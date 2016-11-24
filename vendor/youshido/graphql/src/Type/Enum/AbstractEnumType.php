<?php
/**
 * Date: 07.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Type\Enum;


use Youshido\GraphQL\Config\Object\EnumTypeConfig;
use Youshido\GraphQL\Config\Traits\ConfigAwareTrait;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Traits\AutoNameTrait;
use Youshido\GraphQL\Type\TypeMap;

abstract class AbstractEnumType extends AbstractType
{

    use AutoNameTrait, ConfigAwareTrait;

    /**
     * ObjectType constructor.
     * @param $config
     */
    public function __construct($config = [])
    {
        if (empty($config)) {
            $config['name']   = $this->getName();
            $config['values'] = $this->getValues();
        }

        $this->config = new EnumTypeConfig($config, $this);
    }

    /**
     * @return String predefined type kind
     */
    public function getKind()
    {
        return TypeMap::KIND_ENUM;
    }

    /**
     * @param $value mixed
     *
     * @return bool
     */
    public function isValidValue($value)
    {
        foreach ($this->getConfig()->get('values') as $item) {
            if ($value === $item['name'] || $value === $item['value']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    abstract public function getValues();

    public function serialize($value)
    {
        foreach ($this->getConfig()->get('values') as $valueItem) {
            if ($value === $valueItem['value']) {
                return $valueItem['name'];
            }
        }

        return null;
    }

    public function parseValue($value)
    {
        foreach ($this->getConfig()->get('values') as $valueItem) {
            if ($value === $valueItem['name']) {
                return $valueItem['value'];
            }
        }

        return null;
    }

}
