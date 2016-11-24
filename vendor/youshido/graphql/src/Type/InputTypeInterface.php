<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 9/29/16 10:41 PM
*/

namespace Youshido\GraphQL\Type;


use Youshido\GraphQL\Config\AbstractConfig;

interface InputTypeInterface
{
    /**
     * @return String type name
     */
    public function getName();

    /**
     * @return String predefined type kind
     */
    public function getKind();

    /**
     * @return String type description
     */
    public function getDescription();

    /**
     * Coercing value received as input to current type
     *
     * @param $value
     * @return mixed
     */
    public function parseValue($value);

    /**
     * Coercing result to current type
     *
     * @param $value
     * @return mixed
     */
    public function serialize($value);

    /**
     * @param $value mixed
     *
     * @return bool
     */
    public function isValidValue($value);

    /**
     * @return AbstractConfig
     */
    public function getConfig();
}