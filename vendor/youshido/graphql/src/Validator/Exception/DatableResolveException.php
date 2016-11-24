<?php
/**
 * Date: 22.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Validator\Exception;

class DatableResolveException extends \Exception
{

    /** @var  array */
    protected $data;

    public function __construct($message, $code = 0, $data = [])
    {
        parent::__construct($message, $code);

        $this->setData($data);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

}