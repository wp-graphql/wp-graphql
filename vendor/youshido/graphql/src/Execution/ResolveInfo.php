<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/19/16 8:58 AM
*/

namespace Youshido\GraphQL\Execution;

use Youshido\GraphQL\Execution\Context\ExecutionContextInterface;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Field\FieldInterface;
use Youshido\GraphQL\Parser\Ast\Field;
use Youshido\GraphQL\Type\AbstractType;

class ResolveInfo
{
    /** @var  FieldInterface */
    protected $field;

    /** @var Field[] */
    protected $fieldASTList;

    /** @var ExecutionContextInterface */
    protected $executionContext;

    /**
     * This property is to be used for DI in various scenario
     * Added to original class to keep backward compatibility
     * because of the way AbstractField::resolve has been declared
     *
     * @var mixed $container
     */
    protected $container;

    public function __construct(FieldInterface $field, array $fieldASTList, ExecutionContextInterface $executionContext)
    {
        $this->field            = $field;
        $this->fieldASTList     = $fieldASTList;
        $this->executionContext = $executionContext;
    }

    /**
     * @return ExecutionContextInterface
     */
    public function getExecutionContext()
    {
        return $this->executionContext;
    }

    /**
     * @return FieldInterface
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return Field[]
     */
    public function getFieldASTList()
    {
        return $this->fieldASTList;
    }

    /**
     * @return AbstractType
     */
    public function getReturnType()
    {
        return $this->field->getType();
    }

    public function getContainer()
    {
        return $this->executionContext->getContainer();
    }


}
