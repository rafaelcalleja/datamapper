<?php

namespace rc\Domain\Model\Person;

class Person
{
    private $lastname;
    private $firstname;
    private $numberOfDependent;

    public function __construct($lastname, $firstname, $numberOfDependent)
    {
        $this->lastname = $lastname;
        $this->firstname = $firstname;
        $this->numberOfDependent = $numberOfDependent;
    }
}