<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 10:25 PM
*/

namespace Youshido\GraphQL\Type\InputObject;

use Youshido\GraphQL\Config\Object\InputObjectTypeConfig;

final class InputObjectType extends AbstractInputObjectType
{

    public function __construct($config)
    {
        $this->config = new InputObjectTypeConfig($config, $this, true);
    }

    /**
     * @codeCoverageIgnore
     */
    public function build($config)
    {
    }
}
