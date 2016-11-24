<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 9/29/16 10:32 PM
*/

namespace Youshido\GraphQL\Field;


use Youshido\GraphQL\Type\AbstractType;

interface InputFieldInterface
{
    /**
     * @return AbstractType
     */
    public function getType();

    public function getName();

    public function addArguments($argumentsList);

    public function removeArgument($argumentName);

    public function addArgument($argument, $ArgumentInfo = null);

    /**
     * @return AbstractType[]
     */
    public function getArguments();

    /**
     * @return AbstractType
     */
    public function getArgument($argumentName);

    /**
     * @return boolean
     */
    public function hasArgument($argumentName);

    /**
     * @return boolean
     */
    public function hasArguments();


}