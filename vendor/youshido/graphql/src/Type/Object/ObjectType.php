<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:24 AM
*/

namespace Youshido\GraphQL\Type\Object;

use Youshido\GraphQL\Config\Object\ObjectTypeConfig;

final class ObjectType extends AbstractObjectType
{

    public function __construct(array $config)
    {
        $this->config = new ObjectTypeConfig($config, $this, true);
    }

    /**
     * @codeCoverageIgnore
     */
    public function build($config) { }

    public function getName()
    {
        return $this->getConfigValue('name');
    }
}
