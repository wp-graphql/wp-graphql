<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 4/30/16 9:11 PM
*/

namespace Youshido\GraphQL\Validator\SchemaValidator;

use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;
use Youshido\GraphQL\Validator\Exception\ConfigurationException;

class SchemaValidator
{

    /** @var ConfigValidator */
    private $configValidator = null;
    /**
     * @param AbstractSchema $schema
     *
     * @throws ConfigurationException
     */
    public function validate(AbstractSchema $schema)
    {
        if (!$schema->getQueryType()->hasFields()) {
            throw new ConfigurationException('Schema has to have fields');
        }

        $this->configValidator = ConfigValidator::getInstance();

        foreach ($schema->getQueryType()->getConfig()->getFields() as $field) {
            $this->configValidator->assertValidConfig($field->getConfig());

            if ($field->getType() instanceof AbstractObjectType) {
                $this->assertInterfaceImplementationCorrect($field->getType());
            }
        }
    }

    /**
     * @param AbstractObjectType $type
     *
     * @throws ConfigurationException
     */
    protected function assertInterfaceImplementationCorrect(AbstractObjectType $type)
    {
        if (!$type->getInterfaces()) {
            return;
        }

        foreach ($type->getInterfaces() as $interface) {
            foreach ($interface->getConfig()->getFields() as $intField) {
                $this->assertFieldsIdentical($intField, $type->getConfig()->getField($intField->getName()), $interface);
            }
        }
    }

    /**
     * @param Field                 $intField
     * @param Field                 $objField
     * @param AbstractInterfaceType $interface
     *
     * @return bool
     *
     * @throws ConfigurationException
     */
    protected function assertFieldsIdentical($intField, $objField, AbstractInterfaceType $interface)
    {
        $isValid = true;
        if ($intField->getType()->isCompositeType() !== $objField->getType()->isCompositeType()) {
            $isValid = false;
        }
        if ($intField->getType()->getNamedType()->getName() != $objField->getType()->getNamedType()->getName()) {
            $isValid = false;
        }

        if (!$isValid) {
            throw new ConfigurationException(sprintf('Implementation of %s is invalid for the field %s', $interface->getName(), $objField->getName()));
        }
    }
}
