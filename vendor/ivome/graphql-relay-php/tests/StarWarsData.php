<?php
/**
 * @author: Ivo Meißner
 * Date: 29.02.16
 * Time: 08:34
 */

namespace GraphQLRelay\tests;


class StarWarsData {
    protected static $xwing = [
        'id' => '1',
        'name' => 'X-Wing'
    ];

    protected static $ywing = [
        'id' => '2',
        'name' => 'Y-Wing'
    ];

    protected static $awing = [
        'id' => '3',
        'name' => 'A-Wing'
    ];

    protected static $falcon = [
        'id' => '4',
        'name' => 'Millenium Falcon'
    ];

    protected static $homeOne = [
        'id' => '5',
        'name' => 'Home One'
    ];

    protected static $tieFighter = [
        'id' => '6',
        'name' => 'TIE Fighter'
    ];

    protected static $tieInterceptor = [
        'id' => '7',
        'name' => 'TIE Interceptor'
    ];

    protected static $executor = [
        'id' => '8',
        'name' => 'TIE Interceptor'
    ];

    protected static $rebels = [
        'id' => '1',
        'name' => 'Alliance to Restore the Republic',
        'ships' => ['1', '2', '3', '4', '5']
    ];

    protected static $empire = [
        'id' => '2',
        'name' => 'Galactic Empire',
        'ships' => ['6', '7', '8']
    ];

    protected static $nextShip = 9;

    protected static $data;

    /**
     * Returns the data object
     *
     * @return array $array
     */
    protected static function getData()
    {
        if (self::$data === null) {
            self::$data = [
                'Faction' => [
                    '1' => self::$rebels,
                    '2' => self::$empire
                ],
                'Ship' => [
                    '1' => self::$xwing,
                    '2' => self::$ywing,
                    '3' => self::$awing,
                    '4' => self::$falcon,
                    '5' => self::$homeOne,
                    '6' => self::$tieFighter,
                    '7' => self::$tieInterceptor,
                    '8' => self::$executor
                ]
            ];
        }
        return self::$data;
    }

    /**
     * @param $shipName
     * @param $factionId
     * @return array
     */
    public static function createShip($shipName, $factionId)
    {
        $data = self::getData();

        $newShip = [
            'id' => (string) self::$nextShip++,
            'name' => $shipName
        ];
        $data['Ship'][$newShip['id']] = $newShip;
        $data['Faction'][$factionId]['ships'][] = $newShip['id'];

        // Save
        self::$data = $data;

        return $newShip;
    }

    public static function getShip($id)
    {
        $data = self::getData();
        return $data['Ship'][$id];
    }

    public static function getFaction($id)
    {
        $data = self::getData();
        return $data['Faction'][$id];
    }

    public static function getRebels()
    {
        return self::$rebels;
    }

    public static function getEmpire()
    {
        return self::$empire;
    }
}