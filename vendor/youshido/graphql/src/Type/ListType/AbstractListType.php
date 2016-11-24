<?php
/**
 * Date: 03.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Type\ListType;


use Youshido\GraphQL\Config\Object\ListTypeConfig;
use Youshido\GraphQL\Type\CompositeTypeInterface;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\TypeMap;

abstract class AbstractListType extends AbstractObjectType implements CompositeTypeInterface
{
    /**
     * @var ListTypeConfig
     */
    protected $config;

    public function __construct()
    {
        $this->config = new ListTypeConfig(['itemType' => $this->getItemType()], $this);
    }

    /**
     * @return AbstractObjectType
     */
    abstract public function getItemType();

    public function isValidValue($value)
    {
        $isValid  = is_null($value) || is_array($value) || ($value instanceof \Traversable);
        $itemType = $this->config->get('itemType');

        if ($isValid && $value && $itemType->isInputType()) {
            foreach ($value as $item) {
                $isValid = $itemType->isValidValue($item);

                if (!$isValid) {
                    break;
                }
            }
        }

        return $isValid;
    }

    /**
     * @inheritdoc
     */
    public function build($config)
    {
    }

    public function isCompositeType()
    {
        return true;
    }

    public function getNamedType()
    {
        return $this->getItemType();
    }

    final public function getKind()
    {
        return TypeMap::KIND_LIST;
    }

    public function getTypeOf()
    {
        return $this->getNamedType();
    }

    public function parseValue($value)
    {
        foreach ($value as $keyValue => $valueItem) {
            $value[$keyValue] = $this->getItemType()->parseValue($valueItem);
        }

        return $value;
    }

}
