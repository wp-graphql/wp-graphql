<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/28/15 6:11 PM
*/

namespace Youshido\GraphQL\Validator\ConfigValidator\Rules;


interface ValidationRuleInterface
{
    public function validate($data, $ruleInfo);
}