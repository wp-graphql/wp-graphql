<?php
/**
 * Date: 14.01.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Type\Object;

abstract class AbstractMutationObjectType extends AbstractObjectType
{

    public function getType()
    {
        return $this->getOutputType();
    }

    abstract public function getOutputType();
}
