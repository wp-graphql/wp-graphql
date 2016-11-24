<?php
/**
 * Date: 17.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Config;


use Youshido\GraphQL\Field\Field;

interface TypeConfigInterface
{

    /**
     * @param Field|string $field
     * @param array        $fieldInfo
     */
    public function addField($field, $fieldInfo = null);

    public function getField($name);

    public function removeField($name);

    public function hasField($name);

    public function getFields();

}
