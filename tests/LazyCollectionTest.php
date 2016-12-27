<?php

namespace rc;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\Instantiator\Instantiator;
use Doctrine\Instantiator\InstantiatorInterface;

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
        $entity->id = '1';
        $entity->name = 'myEntity1';

        $relationA = new myRelation();
        $relationB = new myRelation();

        $relationA->name = 'myRelation1';
        $relationB->name = 'myRelation2';

        $relationA->myEntity = $entity;
        $relationB->myEntity = $entity;

        $entity->lazyCollection = [$relationA, $relationB];

        $entityManager = new EntityManager();

        $queryBuilder = new QueryBuilder(
            DriverManager::getConnection(
                [
                    'driver' => 'pdo_sqlite',
                    'path' => __DIR__.'/db.sqlite',
                ]
            )
        );

        $queryBuilder->from('myRelation', 'dbe4');

        $collection = $entityManager->load(myRelation::class, $queryBuilder);

        $this->assertCount(2, $collection);

        $this->assertSame($relationA->name, $collection[0]->name);
        $this->assertSame($relationB->name, $collection[1]->name);

        $manyToOne1 = $collection[0]->myEntity;
        $manyToOne2 = $collection[1]->myEntity;

        $this->assertSame($manyToOne1->name, $entity->name);
        $this->assertSame($manyToOne2->name, $entity->name);

        $this->assertSame($manyToOne1->id, $entity->id);
        $this->assertSame($manyToOne2->id, $entity->id);
    }
}

class myEntity
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $name;

    /**
     * @var myRelation[]
     */
    public $lazyCollection;
}

class myRelation
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var myEntity
     */
    public $myEntity;
}

abstract class BasicMapper implements DataMapperInterface
{
    protected $tableName;

    protected $alias;

    /**
     * @var EntityInterface
     */
    protected $entityClass;

    /**
     * @var OneToManyInterface[]
     */
    protected $oneToMany = [];

    /**
     * @var ManyToOneInterface[]
     */
    protected $manyToOne = [];

    /**
     * @var ColumnInterface[]
     */
    protected $columns = [];

    public function addEntity(EntityInterface $entity)
    {
        $this->entityClass = $entity;
    }
    /**
     * @param OneToManyInterface $relation
     * @return $this
     */
    public function addOneToMany(OneToManyInterface $relation)
    {
        $this->oneToMany[] = $relation;
        return $this;
    }

    /**
     * @param ManyToOneInterface $relation
     */
    public function addManyToOne(ManyToOneInterface $relation)
    {
        $this->manyToOne[] = $relation;
        return $this;
    }

    /**
     * @return ColumnInterface[]
     */
    public function columns()
    {
        return $this->columns;
    }

    /**
     * @return OneToManyInterface[]
     */
    public function oneToMany()
    {
        return $this->oneToMany;
    }

    /**
     * @return ManyToOneInterface[]
     */
    public function manyToOne()
    {
        return $this->manyToOne;
    }


    /**
     * @param ColumnInterface $column
     * @return $this
     */
    public function addColumns(ColumnInterface $column)
    {
        $this->columns[] = $column;
        return $this;
    }

    /**
     * @param $tableName
     * @return $this
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    public function alias()
    {
        if (null === $this->alias)
        {
            $this->alias = substr(md5(static::class), 0, 4);
        }

        return $this->alias;
    }

    /**
     * @return EntityInterface
     */
    public function entity()
    {
        return $this->entityClass;
    }

    /**
     * @return string
     */
    public function tableName()
    {
        return $this->tableName;
    }


}

class ObjectHydrator
{
    /**
     * @var UnitOfWork
     */
    private $unitOfWork;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->unitOfWork = $this->entityManager->getUnitOfWork();
    }

    public function hydrateAll(Context $context, $stmt)
    {
        $models = [];

        /** @var \Doctrine\DBAL\Driver\Statement $stmt */
        while ($row = $stmt->fetch()) {
            $models[] = $this->unitOfWork->getOrCreateEntity(
                $context
                    ->classMetadata()
                    ->entity()
                    ->className(),
                $row);
        }

        return $models;
    }
}

class UnitOfWork
{
    /**
     * @var InstantiatorInterface
     */
    private $instantiator;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @param EntityManagerInterface $entityManager
     * @internal param InstantiatorInterface $instantiator
     */
    public function __construct(EntityManagerInterface $entityManager, InstantiatorInterface $instantiator = null)
    {
        $this->entityManager = $entityManager;
        $this->instantiator = $instantiator ?: new Instantiator();
    }

    public function getOrCreateEntity($className, $data)
    {
        /** @var DataMapperInterface $class */
        $class = $this->entityManager->getClassMetadata($className);
        $entity = $this->instantiator->instantiate($className);

        foreach ($data as $field => $value) {
            foreach($class->columns() as $column){
                /** @var ColumnInterface $column */
                if ( $class->alias().'_'.$column->column() === $field){
                    $class
                        ->entity()
                        ->setReflectionProperty(
                            $entity,
                            $column->property(),
                            $column->getValue($value)
                        );

                    break;
                }
            }

            foreach($class->manyToOne() as $relation){
                /** @var ManyToOneInterface $relation */
                $column = $relation->foreingKey();
                if ( $class->alias().'_'. $column->column() === $field){
                    $model = $this->getOrCreateEntity($relation->mapper()->entity()->className(), $data);

                    $class
                        ->entity()
                        ->setReflectionProperty(
                            $entity,
                            $column->property(),
                            $column->getValue($model)
                        );

                    break;
                }

            }
        }

        return $entity;
    }

}

class Context
{
    protected $identityMap = [];

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var DataMapperInterface
     */
    private $classMetadata;

    public function __construct(DataMapperInterface $classMetadata, QueryBuilder $queryBuilder)
    {
        $this->classMetadata = $classMetadata;
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @return DataMapperInterface
     */
    public function classMetadata()
    {
        return $this->classMetadata;
    }

    /**
     * @return QueryBuilder
     */
    public function queryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @return array
     */
    public function identityMap()
    {
        return $this->identityMap;
    }

    /**
     * @return string
     */
    public function getSelect()
    {
        $queryBuilder = $this->queryBuilder;
        $queryBuilder->resetQueryParts();

        $selectColumns = $this->buildSelect($this->classMetadata->alias(), $this->classMetadata->columns());

        $queryBuilder->addSelect(
            $selectColumns
        );

        $queryBuilder->from(
            $this->classMetadata->tableName(),
            $this->classMetadata->alias()
        );

        $this->identityMap[$this->classMetadata->alias()] = $this->classMetadata;

        $counter = 1;

        foreach($this->classMetadata->oneToMany() as $relation)
        {
            /** @var OneToManyInterface $relation */
            $selectColumns = array_merge($selectColumns, $this->buildSelect($relation->entity()->alias(), $relation->columns()));
            $this->identityMap[$relation->entity()->alias()] = $relation;
            $counter++;
        }

        foreach($this->classMetadata->manyToOne() as $relation)
        {
            // ->leftJoin('u', 'phonenumbers', 'p', 'p.is_primary = 1');
           /** @var ManyToOneInterface $relation */
            $queryBuilder->addSelect(
                $this->buildSelect(
                    $relation->mapper()->alias(),
                    $relation->mapper()->columns()
                )
            );

            $selectColumns = array_merge($selectColumns, $this->buildSelect($relation->mapper()->alias(), $relation->mapper()->columns()));

            $alias = $this->classMetadata->alias();
            $column = $relation->foreingKey();

            $selectColumns = array_merge($selectColumns, [
                "{$alias}.{$column->column()} as {$alias}_{$column->column()}"
            ]);

            $queryBuilder->leftJoin(
                $this->classMetadata->alias(),
                $relation->mapper()->tableName(),
                $relation->mapper()->alias(),
                $queryBuilder->expr()->eq(
                    sprintf("%s.%s", $this->classMetadata->alias(), $relation->foreingKey()->column()),
                    sprintf("%s.%s", $relation->mapper()->alias(), 'id')
                )
            );
        }

        return implode(', ', $selectColumns);
    }


    /**
     * @param $alias
     * @param $columns
     * @return string
     */
    protected function buildSelect($alias, $columns)
    {
        $columnNames = [];

        foreach ($columns as $key => $column) {
            /** @var ColumnInterface $column */
            $columnNames[] = "{$alias}.{$column->column()} as {$alias}_{$column->column()}";
        }

        return $columnNames;
    }

}

class EntityManager implements EntityManagerInterface
{
    private $unitOfWork;

    private $classMetadata;

    private $hydrator;

    public function __construct()
    {
        $this->unitOfWork = new UnitOfWork($this);
        $this->classMetadata = new ClassMetadataCollection();
        $this->hydrator = new ObjectHydrator($this);
    }

    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * @param $className
     * @return DataMapperInterface
     */
    public function getClassMetadata($className)
    {
        return $this->classMetadata->getClassMapper($className);
    }

    /**
     * @param QueryBuilder $queryBuilder
     */
    public function load($entityName, QueryBuilder $queryBuilder)
    {
        $class = $this->getClassMetadata($entityName);

        $context = new Context($class, $queryBuilder);

        $queryBuilder->select(
            $context->getSelect()
        );

        return $this->hydrator->hydrateAll(
            $context,
            $queryBuilder->execute()
        );
    }
}

class ClassMetadataCollection
{
    protected $classes = [];

    public function __construct()
    {
        $this->classes[myEntity::class] = new myEntityMapper();
        $this->classes[myRelation::class] = new myRelationMapper();
    }

    public function getClassMapper($className)
    {
        if ( false === array_key_exists($className, $this->classes) ){
            throw new \InvalidArgumentException("className ({$className}) not found");
        }

        return $this->classes[$className];
    }
}

interface EntityManagerInterface
{
    public function getUnitOfWork();

    public function getClassMetadata($className);

    public function load($entityName, QueryBuilder $queryBuilder);
}

interface EntityInterface
{
    public function className();

    public function setReflectionProperty($object, $propertyName, $value);
}

class DomainEntity implements EntityInterface
{
    /**
     * @var
     */
    private $className;

    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * @return mixed
     */
    public function className()
    {
        return $this->className;
    }

    public function setReflectionProperty($object, $propertyName, $value)
    {
        $reflection = new \ReflectionObject($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}

class myRelationMapper extends BasicMapper
{
    public function __construct()
    {
        $this->setTableName('myRelation');

        $this->addEntity(
            new DomainEntity(myRelation::class)
        );

        $this->addColumns(
            new Column('name', 'name')
        );

        $this->addManyToOne(
            new ManyToOne(new Column('myEntity', 'entity_fk'), myEntityMapper::class)
        );
    }
}


class myEntityMapper extends BasicMapper
{
    public function __construct()
    {
        $this->setTableName('myEntity');

        $this->addEntity(
            new DomainEntity(myEntity::class)
        );

        $this->addColumns(
            new Column('id', 'id')
        );

        $this->addColumns(
            new Column('name', 'name')
        );
        
        $this->addOneToMany(
            new OneToMany('lazyCollection', myRelationMapper::class)
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
class ManyToOne implements ManyToOneInterface
{
    /**
     * @var ColumnInterface
     */
    private $foreingKey;

    /**
     * @var DataMapperInterface
     */
    private $mapper;

    /**
     * @param ColumnInterface $foreignKey
     * @param string $mapper
     */
    public function __construct(ColumnInterface $foreingKey, $mapper)
    {
        $this->foreingKey = $foreingKey;
        $this->mapper = $mapper;
    }

    /**
     * @return ColumnInterface
     */
    public function foreingKey()
    {
        return $this->foreingKey;
    }

    /**
     * @return DataMapperInterface
     */
    public function mapper()
    {
        return new $this->mapper();
    }

}

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

    /**
     * @var ColumnInterface[]
     */
    private $columns;

    /**
     * @param $field
     * @param DataMapperInterface $entity
     */
    public function __construct($field, $entity)
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
        return new $this->entity();
    }

    /**
     * @return ColumnInterface[]
     */
    public function columns()
    {
        return $this->entity()->columns();
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

    public function getValue($value)
    {
        return $value;
    }
}

interface DataMapperInterface
{
    public function addEntity(EntityInterface $entity);

    public function addManyToOne(ManyToOneInterface $relation);

    public function addOneToMany(OneToManyInterface $relation);

    public function addColumns(ColumnInterface $column);

    /**
     * @return EntityInterface
     */
    public function entity();

    /**
     * @return ManyToOneInterface
     */
    public function manyToOne();

    /**
     * @return OneToManyInterface
     */
    public function oneToMany();

    public function columns();

    public function alias();

    public function tableName();
}

interface ColumnInterface
{
    public function property();

    public function column();

    public function getValue($value);
}

interface OneToManyInterface
{
    public function field();

    public function entity();
}

interface ManyToOneInterface
{
    /**
     * @return ColumnInterface
     */
    public function foreingKey();

    /**
     * @return DataMapperInterface
     */
    public function mapper();
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
