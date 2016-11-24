<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/2/15 8:57 PM
*/

namespace Youshido\GraphQL\Type\Object;


use Youshido\GraphQL\Config\Object\ObjectTypeConfig;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\Traits\AutoNameTrait;
use Youshido\GraphQL\Type\Traits\FieldsArgumentsAwareObjectTrait;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Validator\Exception\ResolveException;

/**
 * Class AbstractObjectType
 * @package Youshido\GraphQL\Type\Object
 */
abstract class AbstractObjectType extends AbstractType
{
    use AutoNameTrait, FieldsArgumentsAwareObjectTrait;

    protected $isBuilt = false;

    public function getConfig()
    {
        if (!$this->isBuilt) {
            $this->isBuilt = true;
            $this->build($this->config);
        }

        return $this->config;
    }

    /**
     * ObjectType constructor.
     * @param $config
     */
    public function __construct(array $config = [])
    {
        if (empty($config)) {
            $config['name']       = $this->getName();
            $config['interfaces'] = $this->getInterfaces();
        }

        $this->config = new ObjectTypeConfig($config, $this);
    }

    final public function serialize($value)
    {
        /** why final? */
        /** and why we need this method in *ObjectType? */
        throw new ResolveException('You can not serialize object value directly');
    }

    public function getKind()
    {
        return TypeMap::KIND_OBJECT;
    }

    public function getType()
    {
        return $this->getConfigValue('type', $this);
    }

    public function getNamedType()
    {
        return $this;
    }

    /**
     * @param ObjectTypeConfig $config
     * @return mixed
     */
    abstract public function build($config);

    /**
     * @return AbstractInterfaceType[]
     */
    public function getInterfaces()
    {
        return $this->getConfigValue('interfaces', []);
    }

    public function isValidValue($value)
    {
        return is_array($value) || is_null($value) || is_object($value);
    }

}
