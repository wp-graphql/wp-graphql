<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 11:05 PM
*/

namespace Youshido\GraphQL\Config\Traits;


use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;

/**
 * Class FieldsAwareTrait
 * @package Youshido\GraphQL\Config\Traits
 */
trait FieldsAwareConfigTrait
{
    protected $fields = [];

    public function buildFields()
    {
        if (!empty($this->data['fields'])) {
            $this->addFields($this->data['fields']);
        }
    }

    /**
     * Add fields from passed interface
     * @param AbstractInterfaceType $interfaceType
     * @return $this
     */
    public function applyInterface(AbstractInterfaceType $interfaceType)
    {
        $this->addFields($interfaceType->getFields());

        return $this;
    }

    /**
     * @param array $fieldsList
     * @return $this
     */
    public function addFields($fieldsList)
    {
        foreach ($fieldsList as $fieldName => $fieldConfig) {

            if ($fieldConfig instanceof FieldInterface) {
                $this->fields[$fieldConfig->getName()] = $fieldConfig;
                continue;
            } else {
                $this->addField($fieldName, $this->buildFieldConfig($fieldName, $fieldConfig));
            }
        }

        return $this;
    }

    /**
     * @param FieldInterface|string $field     Field name or Field Object
     * @param mixed                $fieldInfo Field Type or Field Config array
     * @return $this
     */
    public function addField($field, $fieldInfo = null)
    {
        if (!($field instanceof FieldInterface)) {
            $field = new Field($this->buildFieldConfig($field, $fieldInfo));
        }

        $this->fields[$field->getName()] = $field;

        return $this;
    }

    protected function buildFieldConfig($name, $info = null)
    {
        if (!is_array($info)) {
            $info = [
                'type' => $info,
                'name' => $name,
            ];
        } elseif (empty($info['name'])) {
            $info['name'] = $name;
        }

        return $info;
    }

    /**
     * @param $name
     *
     * @return Field
     */
    public function getField($name)
    {
        return $this->hasField($name) ? $this->fields[$name] : null;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasField($name)
    {
        return array_key_exists($name, $this->fields);
    }

    public function hasFields()
    {
        return !empty($this->fields);
    }

    /**
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    public function removeField($name)
    {
        if ($this->hasField($name)) {
            unset($this->fields[$name]);
        }

        return $this;
    }
}
