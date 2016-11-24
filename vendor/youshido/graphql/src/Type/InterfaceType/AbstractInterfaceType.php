<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/5/15 12:12 AM
*/

namespace Youshido\GraphQL\Type\InterfaceType;


use Youshido\GraphQL\Config\Object\InterfaceTypeConfig;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\Traits\AutoNameTrait;
use Youshido\GraphQL\Type\Traits\FieldsAwareObjectTrait;
use Youshido\GraphQL\Type\TypeMap;

abstract class AbstractInterfaceType extends AbstractType
{
    use FieldsAwareObjectTrait, AutoNameTrait;

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
     *
     * @param $config
     */
    public function __construct($config = [])
    {
        if (empty($config)) {
            $config['name'] = $this->getName();
        }

        $this->config = new InterfaceTypeConfig($config, $this);
    }

    abstract public function resolveType($object);

    /**
     * @param InterfaceTypeConfig $config
     *
     * @return mixed
     */
    abstract public function build($config);

    public function getKind()
    {
        return TypeMap::KIND_INTERFACE;
    }

    public function getNamedType()
    {
        return $this;
    }

    public function isValidValue($value)
    {
        return is_array($value) || is_null($value) || is_object($value);
    }

}
