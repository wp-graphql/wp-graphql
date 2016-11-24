<?php
/**
 * Date: 03.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Introspection;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Introspection\Traits\TypeCollectorTrait;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\CompositeTypeInterface;
use Youshido\GraphQL\Type\Enum\AbstractEnumType;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\Union\AbstractUnionType;

class QueryType extends AbstractObjectType
{

    use TypeCollectorTrait;

    /**
     * @return String type name
     */
    public function getName()
    {
        return '__Type';
    }

    public function resolveOfType(AbstractType $value)
    {
        if ($value instanceof CompositeTypeInterface) {
            return $value->getTypeOf();
        }

        return null;
    }

    public function resolveInputFields($value)
    {
        if ($value instanceof AbstractInputObjectType) {
            /** @var AbstractObjectType $value */
            return $value->getConfig()->getFields();
        }

        return null;
    }

    public function resolveEnumValues($value, $args)
    {
        /** @var $value AbstractType|AbstractEnumType */
        if ($value && $value->getKind() == TypeMap::KIND_ENUM) {
            $data = [];
            foreach ($value->getValues() as $enumValue) {
                if(!$args['includeDeprecated'] && (isset($enumValue['isDeprecated']) && $enumValue['isDeprecated'])) {
                    continue;
                }

                if (!array_key_exists('description', $enumValue)) {
                    $enumValue['description'] = '';
                }
                if (!array_key_exists('isDeprecated', $enumValue)) {
                    $enumValue['isDeprecated'] = false;
                }
                if (!array_key_exists('deprecationReason', $enumValue)) {
                    $enumValue['deprecationReason'] = '';
                }

                $data[] = $enumValue;
            }

            return $data;
        }

        return null;
    }

    public function resolveFields($value, $args)
    {
        /** @var AbstractType $value */
        if (!$value ||
            in_array($value->getKind(), [TypeMap::KIND_SCALAR, TypeMap::KIND_UNION, TypeMap::KIND_INPUT_OBJECT, TypeMap::KIND_ENUM])
        ) {
            return null;
        }

        /** @var AbstractObjectType $value */
        return array_filter($value->getConfig()->getFields(), function ($field) use ($args) {
            /** @var $field Field */
            if (in_array($field->getName(), ['__type', '__schema']) || (!$args['includeDeprecated'] && $field->isDeprecated())) {
                return false;
            }

            return true;
        });
    }

    public function resolveInterfaces($value)
    {
        /** @var $value AbstractType */
        if ($value->getKind() == TypeMap::KIND_OBJECT) {
            /** @var $value AbstractObjectType */
            return $value->getConfig()->getInterfaces() ?: [];
        }

        return null;
    }

    public function resolvePossibleTypes($value, $args, ResolveInfo $info)
    {
        /** @var $value AbstractObjectType */
        if ($value->getKind() == TypeMap::KIND_INTERFACE) {
            $this->collectTypes($info->getExecutionContext()->getSchema()->getQueryType());

            $possibleTypes = [];
            foreach ($this->types as $type) {
                /** @var $type AbstractObjectType */
                if ($type->getKind() == TypeMap::KIND_OBJECT) {
                    $interfaces = $type->getConfig()->getInterfaces();

                    if ($interfaces) {
                        foreach ($interfaces as $interface) {
                            if ($interface->getName() == $value->getName()) {
                                $possibleTypes[] = $type;
                            }
                        }
                    }
                }
            }

            return $possibleTypes;
        } elseif ($value->getKind() == TypeMap::KIND_UNION) {
            /** @var $value AbstractUnionType */
            return $value->getTypes();
        }

        return null;
    }

    public function build($config)
    {
        $config
            ->addField('name', TypeMap::TYPE_STRING)
            ->addField('kind', new NonNullType(TypeMap::TYPE_STRING))
            ->addField('description', TypeMap::TYPE_STRING)
            ->addField('ofType', [
                'type'    => new QueryType(),
                'resolve' => [$this, 'resolveOfType']
            ])
            ->addField(new Field([
                'name'    => 'inputFields',
                'type'    => new ListType(new NonNullType(new InputValueType())),
                'resolve' => [$this, 'resolveInputFields']
            ]))
            ->addField(new Field([
                'name'    => 'enumValues',
                'args'    => [
                    'includeDeprecated' => [
                        'type'    => new BooleanType(),
                        'default' => false
                    ]
                ],
                'type'    => new ListType(new NonNullType(new EnumValueType())),
                'resolve' => [$this, 'resolveEnumValues']
            ]))
            ->addField(new Field([
                'name'    => 'fields',
                'args'    => [
                    'includeDeprecated' => [
                        'type'    => new BooleanType(),
                        'default' => false
                    ]
                ],
                'type'    => new ListType(new NonNullType(new FieldType())),
                'resolve' => [$this, 'resolveFields']
            ]))
            ->addField(new Field([
                'name'    => 'interfaces',
                'type'    => new ListType(new NonNullType(new QueryType())),
                'resolve' => [$this, 'resolveInterfaces']
            ]))
            ->addField('possibleTypes', [
                'type'    => new ListType(new NonNullType(new QueryType())),
                'resolve' => [$this, 'resolvePossibleTypes']
            ]);
    }

}
