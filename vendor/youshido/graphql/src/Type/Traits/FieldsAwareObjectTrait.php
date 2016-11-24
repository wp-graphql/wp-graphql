<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 5:07 PM 5/14/16
 */

namespace Youshido\GraphQL\Type\Traits;


use Youshido\GraphQL\Config\Traits\ConfigAwareTrait;

trait FieldsAwareObjectTrait
{
    use ConfigAwareTrait;

    public function addFields($fieldsList)
    {
        $this->getConfig()->addFields($fieldsList);

        return $this;
    }

    public function addField($field, $fieldInfo = null)
    {
        $this->getConfig()->addField($field, $fieldInfo);

        return $this;
    }

    public function getFields()
    {
        return $this->getConfig()->getFields();
    }

    public function getField($fieldName)
    {
        return $this->getConfig()->getField($fieldName);
    }

    public function hasField($fieldName)
    {
        return $this->getConfig()->hasField($fieldName);
    }

    public function hasFields()
    {
        return $this->getConfig()->hasFields();
    }

}
