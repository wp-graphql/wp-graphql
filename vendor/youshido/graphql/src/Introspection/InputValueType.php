<?php
/**
 * Date: 03.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Introspection;

use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeInterface;
use Youshido\GraphQL\Type\TypeMap;

class InputValueType extends AbstractObjectType
{
    /**
     * @param AbstractSchema|Field $value
     *
     * @return TypeInterface
     */
    public function resolveType($value)
    {
        return $value->getConfig()->getType();
    }

    /**
     * @param AbstractSchema|Field $value
     *
     * @return string|null
     *
     * //todo implement value printer
     */
    public function resolveDefaultValue($value)
    {
        $resolvedValue = $value->getConfig()->getDefaultValue();

        return $resolvedValue === null ? $resolvedValue : json_encode($resolvedValue);
    }

    public function build($config)
    {
        $config
            ->addField('name', new NonNullType(TypeMap::TYPE_STRING))
            ->addField('description', TypeMap::TYPE_STRING)
            ->addField(new Field([
                'name'    => 'type',
                'type'    => new NonNullType(new QueryType()),
                'resolve' => [$this, 'resolveType']
            ]))
            ->addField('defaultValue', [
                'type' => TypeMap::TYPE_STRING,
                'resolve' => [$this, 'resolveDefaultValue']
            ]);
    }

    /**
     * @return string type name
     */
    public function getName()
    {
        return '__InputValue';
    }
}
