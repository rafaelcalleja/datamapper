<?php

namespace rc\Infrastructure\Persistence\ORM;

use rc\Domain\Model\Person\Person;

class PersonMapper extends AbstractMapper
{
    const COLUMNS = "id, lastname, firstname, number_of_dependents";

    protected function findStatement() {
        return "SELECT " . self::COLUMNS .
        " FROM people" .
        " WHERE id = ?";
    }

    public function find($id) {
        return new Person($this->abstractFind($id));
    }

    public function find2($id) {
        return find($id);
    }

    protected function doLoad($id, $resultSet)
    {
        // TODO: Implement doLoad() method.
    }
}