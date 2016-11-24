<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 1:22 AM
*/

namespace Youshido\GraphQL\Type\ListType;


use Youshido\GraphQL\Config\Object\ListTypeConfig;

final class ListType extends AbstractListType
{

    public function __construct($itemType)
    {
        $this->config = new ListTypeConfig(['itemType' => $itemType], $this, true);
    }

    public function getItemType()
    {
        return $this->getConfig()->get('itemType');
    }

    public function getName()
    {
        return null;
    }
}
