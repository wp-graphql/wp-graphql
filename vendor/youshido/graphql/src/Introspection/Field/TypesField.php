<?php
/**
 * Date: 16.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Introspection\Field;


use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Introspection\QueryType;
use Youshido\GraphQL\Introspection\Traits\TypeCollectorTrait;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class TypesField extends AbstractField
{

    use TypeCollectorTrait;

    /**
     * @return AbstractObjectType
     */
    public function getType()
    {
        return new ListType(new QueryType());
    }

    public function getName()
    {
        return 'types';
    }

    public function resolve($value, array $args, ResolveInfo $info)
    {
        /** @var $value AbstractSchema $a */
        $this->types = [];
        $this->collectTypes($value->getQueryType());

        if ($value->getMutationType()->hasFields()) {
            $this->collectTypes($value->getMutationType());
        }

        foreach ($value->getTypesList()->getTypes() as $type) {
          $this->collectTypes($type);
        }

        return array_values($this->types);
    }

}
