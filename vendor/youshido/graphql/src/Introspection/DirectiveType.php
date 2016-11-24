<?php
/**
 * Date: 16.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Introspection;

use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeMap;

class DirectiveType extends AbstractObjectType
{

    /**
     * @return String type name
     */
    public function getName()
    {
        return '__Directive';
    }

    public function build($config)
    {
        $config
            ->addField('name', new NonNullType(TypeMap::TYPE_STRING))
            ->addField('description', TypeMap::TYPE_STRING)
            ->addField(new Field([
                'name' => 'args',
                'type' => new ListType(new InputValueType())
            ]))
            ->addField('onOperation', TypeMap::TYPE_BOOLEAN)
            ->addField('onFragment', TypeMap::TYPE_BOOLEAN)
            ->addField('onField', TypeMap::TYPE_BOOLEAN);
    }
}
