<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/30/15 1:30 AM
*/

namespace Youshido\GraphQL\Config\Field;

use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Config\Traits\ArgumentsAwareConfigTrait;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeInterface;
use Youshido\GraphQL\Type\TypeService;

class FieldConfig extends AbstractConfig
{

    use ArgumentsAwareConfigTrait;

    public function getRules()
    {
        return [
            'name'              => ['type' => TypeService::TYPE_STRING, 'final' => true],
            'type'              => ['type' => TypeService::TYPE_GRAPHQL_TYPE, 'final' => true],
            'args'              => ['type' => TypeService::TYPE_ARRAY],
            'description'       => ['type' => TypeService::TYPE_STRING],
            'resolve'           => ['type' => TypeService::TYPE_CALLABLE],
            'isDeprecated'      => ['type' => TypeService::TYPE_BOOLEAN],
            'deprecationReason' => ['type' => TypeService::TYPE_STRING],
            'cost'              => ['type' => TypeService::TYPE_ANY]
        ];
    }

    protected function build()
    {
        $this->buildArguments();
    }

    /**
     * @return TypeInterface|AbstractObjectType
     */
    public function getType()
    {
        return $this->data['type'];
    }

}
