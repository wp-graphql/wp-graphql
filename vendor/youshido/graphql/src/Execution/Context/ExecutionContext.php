<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/19/16 9:00 AM
*/

namespace Youshido\GraphQL\Execution\Context;


use Youshido\GraphQL\Execution\Container\ContainerInterface;
use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Introspection\Field\SchemaField;
use Youshido\GraphQL\Introspection\Field\TypeDefinitionField;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Validator\ErrorContainer\ErrorContainerTrait;
use Youshido\GraphQL\Validator\SchemaValidator\SchemaValidator;

class ExecutionContext implements ExecutionContextInterface
{

    use ErrorContainerTrait;

    /** @var AbstractSchema */
    private $schema;

    /** @var Request */
    private $request;

    /** @var ContainerInterface */
    private $container;

    /**
     * ExecutionContext constructor.
     *
     * @param AbstractSchema $schema
     */
    public function __construct(AbstractSchema $schema)
    {
        $this->schema = $schema;
        $this->validateSchema();

        $this->introduceIntrospectionFields();
    }

    protected function validateSchema()
    {
        try {
            (new SchemaValidator())->validate($this->schema);
        } catch (\Exception $e) {
            $this->addError($e);
        };
    }

    protected function introduceIntrospectionFields()
    {
        $schemaField = new SchemaField();
        $this->schema->addQueryField($schemaField);
        $this->schema->addQueryField(new TypeDefinitionField());
    }

    /**
     * @return AbstractSchema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @param AbstractSchema $schema
     *
     * @return $this
     */
    public function setSchema(AbstractSchema $schema)
    {
        $this->schema = $schema;

        return $this;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    public function get($id)
    {
        return $this->container->get($id);
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return $this
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;

        return $this;
    }
}
