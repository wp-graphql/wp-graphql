<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/5/15 12:18 AM
*/

namespace Youshido\GraphQL\Config\Object;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Config\Traits\ArgumentsAwareConfigTrait;
use Youshido\GraphQL\Config\Traits\FieldsAwareConfigTrait;
use Youshido\GraphQL\Config\TypeConfigInterface;
use Youshido\GraphQL\Type\TypeService;
use Youshido\GraphQL\Validator\Exception\ConfigurationException;

class InterfaceTypeConfig extends AbstractConfig implements TypeConfigInterface
{
    use FieldsAwareConfigTrait, ArgumentsAwareConfigTrait;

    public function getRules()
    {
        return [
            'name'        => ['type' => TypeService::TYPE_STRING, 'final' => true],
            'fields'      => ['type' => TypeService::TYPE_ARRAY_OF_FIELDS_CONFIG, 'final' => true],
            'description' => ['type' => TypeService::TYPE_STRING],
            'resolveType' => ['type' => TypeService::TYPE_CALLABLE, 'final' => true],
        ];
    }

    protected function build()
    {
        $this->buildFields();
    }

    public function resolveType($object)
    {
        $callable = $this->get('resolveType');

        if ($callable && is_callable($callable)) {
            return call_user_func_array($callable, [$object]);
        } elseif (is_callable([$this->contextObject, 'resolveType'])) {
            return $this->contextObject->resolveType($object);
        }

        throw new ConfigurationException('There is no valid resolveType for ' . $this->getName());
    }
}
