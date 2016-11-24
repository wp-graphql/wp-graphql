<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 11:26 PM
*/

namespace Youshido\GraphQL\Field;


use Youshido\GraphQL\Type\Object\AbstractObjectType;

final class InputField extends AbstractInputField
{

    protected $isFinal = false;

    /**
     * @return AbstractObjectType
     */
    public function getType()
    {
        return $this->getConfigValue('type');
    }
}
