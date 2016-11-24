<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/10/16 11:23 PM
*/

namespace Youshido\GraphQL\Relay\Field;


use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Relay\Node;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\IdType;

class GlobalIdField extends AbstractField
{

    /** @var  string */
    protected $typeName;

    /**
     * @param string $typeName
     */
    public function __construct($typeName)
    {
        $this->typeName = $typeName;

        $config = [
            'type'    => $this->getType(),
            'name'    => $this->getName(),
            'resolve' => [$this, 'resolve']
        ];

        parent::__construct($config);
    }

    public function getName()
    {
        return 'id';
    }

    public function getDescription()
    {
        return 'The ID of an object';
    }

    public function getType()
    {
        return new NonNullType(new IdType());
    }

    /**
     * @inheritdoc
     */
    public function resolve($value, array $args, ResolveInfo $info)
    {
        return $value ? Node::toGlobalId($this->typeName, $value['id']) : null;
    }
}
