<?php

include_once dirname(__FILE__) . "/../../public/config.php";
include_once dirname(__FILE__) . "/../defines.php";

class CollectionBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var inMemoryRepository
     */
    protected $entityRepository;

    /**
     * @var inMemoryRepository
     */
    protected $relationRepository;

    public function setUp()
    {
        $this->entityRepository = new inMemoryRepository(
            [
                ['id' => 1]
            ]
        );

        $this->relationRepository = new inMemoryRepository(
            [
                ['name' => 'nameA', 'entity_fk' => 1],
                ['name' => 'nameB', 'entity_fk' => 1]
            ]
        );

    }
    public function testOneToManyLoad()
    {

        $entity = new myEntity();
        $entity->id = 1;

        $relationA = new myRelation();
        $relationB = new myRelation();

        $relationA->name = 'nameA';
        $relationB->name = 'nameB';

        $entity->lazyCollection = [$relationA, $relationB];

    }
}

class myEntity
{
    public $id;

    /**
     * @var \Dokify\Domain\Company\Invitation\CompanyInvitationCollectionInterface
     */
    public $lazyCollection;
}

class myRelation
{
    public $name;
}

abstract class BasicMapper implements DataMapperInterface
{
    /**
     * @var OneToManyInterface[]
     */
    protected $oneToMany;

    /**
     * @var ColumnInterface[]
     */
    protected $columns;

    public function addOneToMany(OneToManyInterface $relation)
    {
        $this->oneToMany[] = $relation;
        return $this;
    }

    /**
     * @return OneToManyInterface[]
     */
    public function oneToMany()
    {
        return $this->oneToMany;
    }

    public function addColumns(ColumnInterface $column)
    {
        $this->columns[] = $column;
        return $this;
    }
}

class myRelationMapper extends BasicMapper
{
    public function __construct()
    {
        $this->addColumns(
            new Column('name', 'name')
        );
    }
}


class myEntityMapper extends BasicMapper
{
    public function __construct()
    {
        $this->addColumns(
            new Column('id', 'id')
        );
        
        $this->addOneToMany(
            new OneToMany('lazyCollection', new myRelationMapper())
        );
    }

}

/**
 CREATE TABLE IF NOT EXISTS `mydb`.`relation` (
`id` INT NOT NULL AUTO_INCREMENT,
`name` VARCHAR(45) NULL,
`entity_id` INT NOT NULL,
PRIMARY KEY (`id`, `entity_id`),
INDEX `fk_relation_entity_idx` (`entity_id` ASC),
CONSTRAINT `fk_relation_entity`
FOREIGN KEY (`entity_id`)
REFERENCES `mydb`.`entity` (`id`)
ON DELETE NO ACTION
ON UPDATE NO ACTION)
ENGINE = InnoDB
 */

class OneToMany implements OneToManyInterface
{
    /**
     * @var
     */
    private $field;

    /**
     * @var DataMapperInterface
     */
    private $entity;

    public function __construct($field, DataMapperInterface $entity)
    {
        $this->field = $field;
        $this->entity = $entity;
    }

    /**
     * @return mixed
     */
    public function field()
    {
        return $this->field;
    }

    /**
     * @return DataMapperInterface
     */
    public function entity()
    {
        return $this->entity;
    }

}

class Column implements ColumnInterface
{
    /**
     * @var string
     */
    private $property;

    /**
     * @var string
     */
    private $column;

    /**
     * @param $property
     * @param $column
     */
    public function __construct($property, $column)
    {
        $this->property = $property;
        $this->column = $column;
    }

    public function property()
    {
        return $this->property;
    }

    public function column()
    {
        return $this->column;
    }
}

interface DataMapperInterface
{
    public function addOneToMany(OneToManyInterface $relation);

    public function addColumns(ColumnInterface $column);
}

interface ColumnInterface
{
    public function property();

    public function column();
}

interface OneToManyInterface
{
    public function field();

    public function entity();
}

class inMemoryRepository
{
    /**
     * @var array
     */
    private $data = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @param int $id
     *
     * @return array|null
     */
    public function find($id)
    {
        if (isset($this->data[$id])) {
            return $this->data[$id];
        }

        return null;
    }
}
