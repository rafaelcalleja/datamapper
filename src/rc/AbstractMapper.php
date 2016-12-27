<?php

namespace rc;

use Doctrine\DBAL\Connection;

abstract class AbstractMapper
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \SplObjectStorage
     */
    private $loadedMap;

    public function __construct(Connection $connection, \SplObjectStorage $loadedMap = null)
    {
        $this->connection = $connection;
        $this->loadedMap = $loadedMap ?: new \SplObjectStorage();
    }

    abstract protected function findStatement();

    protected function abstractFind($id) {

        $result = !$this->loadedMap->offsetExists($id) ?: $this->loadedMap->offsetGet($id);

        if ($result != null) {
            return $result;
        }
            $findStatement = null;
        try {
            /*findStatement = DB.prepare(findStatement());
            findStatement.setLong(1, id.longValue());
            ResultSet rs = findStatement.executeQuery();
            rs.next();
            result = load(rs);*/
            return $result;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function load($resultSet)
    {

        $id = $resultSet->getLong(1);

        if ($this->loadedMap->offsetExists($id)){
            return $this->loadedMap->offsetGet($id);
        }

        $result = $this->doLoad($id, $resultSet);
        $this->loadedMap->offsetSet($id, $result);

        return $result;
    }

    abstract protected function doLoad($id, $resultSet);
}