<?php
/**
 * Date: 17.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\GraphQL\Relay\Connection;


class ArrayConnection
{

    const PREFIX = 'arrayconnection:';

    public static function cursorForObjectInConnection($data, $object)
    {
        if (!is_array($data)) return null;

        $index = array_search($object, $data);
        return $index === false ? null : self::offsetToCursor($index);
    }

    /**
     * @param $offset int
     * @return string
     */
    public static function offsetToCursor($offset)
    {
        return base64_encode(self::PREFIX . $offset);
    }

    /**
     * @param $cursor string
     *
     * @return int|null
     */
    public static function cursorToOffset($cursor)
    {
        if ($decoded = base64_decode($cursor)) {
            return (int)substr($decoded, strlen(self::PREFIX));
        }

        return null;
    }

    public static function cursorToOffsetWithDefault($cursor, $default)
    {
        if (!is_string($cursor)) {
            return $default;
        }

        $offset = self::cursorToOffset($cursor);

        return is_null($offset) ? $default : $offset;
    }

    public static function connectionFromArray(array $data, array $args = [])
    {
        return self::connectionFromArraySlice($data, $args, 0, count($data));
    }

    public static function connectionFromArraySlice(array $data, array $args, $sliceStart, $arrayLength)
    {
        $after  = isset($args['after']) ? $args['after'] : null;
        $before = isset($args['before']) ? $args['before'] : null;
        $first  = isset($args['first']) ? $args['first'] : null;
        $last   = isset($args['last']) ? $args['last'] : null;

        $sliceEnd = $sliceStart + count($data);

        $beforeOffset = ArrayConnection::cursorToOffsetWithDefault($before, $arrayLength);
        $afterOffset  = ArrayConnection::cursorToOffsetWithDefault($after, -1);

        $startOffset = max($sliceStart - 1, $afterOffset, -1) + 1;
        $endOffset   = min($sliceEnd, $beforeOffset, $arrayLength);

        if ($first) {
            $endOffset = min($endOffset, $startOffset + $first);
        }

        if ($last) {
            $startOffset = max($startOffset, $endOffset - $last);
        }

        $arraySliceStart    = max($startOffset - $sliceStart, 0);
        $arraySliceEnd      = count($data) - ($sliceEnd - $endOffset) - $arraySliceStart;

        $slice = array_slice($data, $arraySliceStart, $arraySliceEnd, true);
        $edges = array_map(['self', 'edgeForObjectWithIndex'], $slice, array_keys($slice));

        $firstEdge  = array_key_exists(0, $edges) ? $edges[0] : null;
        $lastEdge   = count($edges) > 0 ? $edges[count($edges) - 1] : null;
        $lowerBound = $after ? $afterOffset + 1 : 0;
        $upperBound = $before ? $beforeOffset : $arrayLength;

        return [
            'edges'    => $edges,
            'pageInfo' => [
                'startCursor'     => $firstEdge ? $firstEdge['cursor'] : null,
                'endCursor'       => $lastEdge ? $lastEdge['cursor'] : null,
                'hasPreviousPage' => $last ? $startOffset > $lowerBound : false,
                'hasNextPage'     => $first ? $endOffset < $upperBound : false
            ]
        ];
    }

    public static function edgeForObjectWithIndex($object, $index)
    {
        return [
            'cursor' => ArrayConnection::offsetToCursor($index),
            'node'   => $object
        ];
    }

}