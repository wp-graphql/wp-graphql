<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/5/16 9:24 PM
*/

namespace Youshido\GraphQL\Schema;


use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\SchemaTypesList;
use Youshido\GraphQL\Type\TypeInterface;

abstract class AbstractSchema
{

    /** @var SchemaConfig */
    protected $config;

    public function __construct($config = [])
    {
        if (!array_key_exists('query', $config)) {
            $config['query'] = new InternalSchemaQueryObject(['name' => $this->getName() . 'Query']);
        }
        if (!array_key_exists('mutation', $config)) {
            $config['mutation'] = new InternalSchemaMutationObject(['name' => $this->getName() . 'Mutation']);
        }
        if (!array_key_exists('types', $config)) {
          $config['types'] = [];
        }

        $this->config = new SchemaConfig($config, $this);

        $this->build($this->config);
    }

    abstract public function build(SchemaConfig $config);

    public function addQueryField($field, $fieldInfo = null)
    {
        $this->getQueryType()->addField($field, $fieldInfo);
    }

    public function addMutationField($field, $fieldInfo = null)
    {
        $this->getMutationType()->addField($field, $fieldInfo);
    }

    final public function getQueryType()
    {
        return $this->config->getQuery();
    }

    final public function getMutationType()
    {
        return $this->config->getMutation();
    }

    /**
     * @return SchemaTypesList
     */
    public function getTypesList()
    {
        return $this->config->getTypesList();
    }

    public function getName()
    {
        $defaultName = 'RootSchema';

        return $this->config ? $this->config->get('name', $defaultName) : $defaultName;
    }
}
