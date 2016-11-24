<?php
/**
 * Date: 16.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Introspection\Field;


use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Introspection\SchemaType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class SchemaField extends AbstractField
{
    /**
     * @return AbstractObjectType
     */
    public function getType()
    {
        return new SchemaType();
    }

    public function getName()
    {
        return '__schema';
    }

    public function resolve($value, array $args, ResolveInfo $info)
    {
        return $info->getExecutionContext()->getSchema();
    }


}
