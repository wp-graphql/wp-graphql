<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/1/16 11:19 AM
*/

namespace Youshido\GraphQL\Config\Traits;


/**
 * Class ConfigCallTrait
 * @package    Youshido\GraphQL\Config\Traits
 *
 * @deprecated This class is not used anywhere in the framework anymore.
 *             Replace it with the new traits available.
 *             To be deleted in the next release
 */
trait ConfigCallTrait
{
    use ConfigAwareTrait;

    public function getField(string $fieldName)
    {
        return $this->getConfig()->getField($fieldName);
    }

    public function __call($method, $arguments)
    {
        $propertyName     = false;
        $passAlongMethods = ['hasField', 'addField', 'addFields', 'removeField', 'getFields', 'hasFields', 'getField',
            'addArgument', 'addArguments', 'getNamedType'];

        if (in_array($method, $passAlongMethods)) {

            return call_user_func_array([$this->getConfig(), $method], $arguments);
        } elseif (substr($method, 0, 3) == 'get') {
            $propertyName = lcfirst(substr($method, 3));
        } elseif (substr($method, 0, 3) == 'set') {
            $propertyName = lcfirst(substr($method, 3));
            $this->getConfig()->set($propertyName, $arguments[0]);

            return $this;
        } elseif (substr($method, 0, 2) == 'is') {
            $propertyName = lcfirst(substr($method, 2));
        }
        if (in_array($propertyName, ['name', 'description', 'deprecationReason', 'isDeprecated', 'field', 'type'])) {
            return $this->getConfig()->get($propertyName);
        }

        throw new \Exception('Call to undefined method ' . $method);
    }

}
