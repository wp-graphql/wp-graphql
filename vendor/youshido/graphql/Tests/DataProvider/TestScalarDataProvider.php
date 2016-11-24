<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/28/15 12:05 PM
*/

namespace Youshido\Tests\DataProvider;

class TestScalarDataProvider
{


    public static function getsList()
    {
        return [
            "Int",
            "String",
            "Boolean",
            "Float",
            "Id"
        ];
    }

    public static function getIntTestData()
    {
        /** input, serialization, isValid */
        return [
            [1, 1, true],
            [0, 0, true],
            [-1, -1, true],
            [0.1, 0, false],
            [1.1, 1, false],
            [[1], 1, false],
            [1e5, 100000, false],
            ["1", 1, false],
            [PHP_INT_MAX - 1, PHP_INT_MAX - 1, true],
            [~PHP_INT_MAX + 1, ~PHP_INT_MAX + 1, true],
            [1e100, null, false],
            [-1e100, null, false],
            ['-1.1', -1, false],
            ['one', null, false],
            [null, null, true],
            [false, 0, false],
            [true, 1, false],
        ];
    }

    public static function getFloatTestData()
    {
        return [
            [1, 1.0, true],
            [0, 0.0, true],
            [-1, -1, true],
            [0.1, 0.1, true],
            [1.1, 1.1, true],
            ['-1.1', -1.1, false],
            ['one', null, false],
            [false, 0.0, false],
            [null, null, true],
            [true, 1.0, false],
        ];
    }

    public static function getStringTestData()
    {
        return [
            ["string", "string", true],
            [1, "1", true],
            [1.1, "1.1", true],
            [null, null, true],
            [true, "true", true],
            [false, "false", true],
            [[], null, false],
        ];
    }

    public static function getBooleanTestData()
    {
        return [
            ["string", true, false],
            ["", false, false],
            [1, true, false],
            [0, false, false],
            [null, false, true],
            [true, true, true],
            [false, false, true],
            [null, null, true],
            ["true", true, false],
            ["false", false, false],
        ];
    }

    public static function getIdTestData()
    {
        return [
            ["string-id", "string-id", true],
            ["", null, true],
            [1, "1", true],
        ];
    }

    public static function getDatetimeTestData()
    {
        return [
            [null, null, false],
            [new \DateTime('now'), date('Y-m-d H:i:s'), true],
        ];
    }

    public static function getDatetimetzTestData()
    {
        return [
            [null, null, false],
            [new \DateTime('now'), date('r'), true],
        ];
    }

    public static function getDateTestData()
    {
        return [
            [null, null, false],
            [new \DateTime('now'), date('Y-m-d'), true],
        ];
    }


    public static function getTimestampTestData()
    {
        return [
            [new \DateTime('now'), time(), true],
            [null, null, false],
        ];
    }

}
