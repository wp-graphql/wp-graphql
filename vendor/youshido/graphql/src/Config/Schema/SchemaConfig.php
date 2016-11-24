<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/28/15 3:53 PM
*/

namespace Youshido\GraphQL\Config\Schema;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\SchemaTypesList;
use Youshido\GraphQL\Type\TypeService;

class SchemaConfig extends AbstractConfig
{

    /**
     * @var SchemaTypesList
     */
    private $typesList;

    public function __construct(array $configData, $contextObject = null, $finalClass = false)
    {
        $this->typesList = new SchemaTypesList();
        parent::__construct($configData, $contextObject, $finalClass);
    }


    public function getRules()
    {
        return [
            'query'    => ['type' => TypeService::TYPE_OBJECT_TYPE, 'required' => true],
            'mutation' => ['type' => TypeService::TYPE_OBJECT_TYPE],
            'types'    => ['type' => TypeService::TYPE_ARRAY],
            'name'     => ['type' => TypeService::TYPE_STRING],
        ];
    }

    protected function build()
    {
        parent::build();
        if (!empty($this->data['types'])) {
            $this->typesList->addTypes($this->data['types']);
        }
    }


    /**
     * @return AbstractObjectType
     */
    public function getQuery()
    {
        return $this->data['query'];
    }

    /**
     * @param $query AbstractObjectType
     *
     * @return SchemaConfig
     */
    public function setQuery($query)
    {
        $this->data['query'] = $query;

        return $this;
    }

    /**
     * @return ObjectType
     */
    public function getMutation()
    {
        return $this->get('mutation');
    }

    /**
     * @param $query AbstractObjectType
     *
     * @return SchemaConfig
     */
    public function setMutation($query)
    {
        $this->data['mutation'] = $query;

        return $this;
    }

    public function getName()
    {
        return $this->get('name', 'RootSchema');
    }

    public function getTypesList()
    {
        return $this->typesList;
    }

}
