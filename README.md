# Database
PHP database access

## Installation

### Composer

Install this library using composer:

```
composer require ceus-media/Database
```

Within your code, load library:

```php
require_once 'vendor/autoload.php';
```

## Code Examples using PDO

### Database Connection

```php
$dbDriver	= 'mysql';
$dbName		= 'myDatabase';
$dbUsername	= 'myDatabaseUser';
$dbPassword	= 'myDatabasePassword';

$dbc	= new \CeusMedia\Database\PDO\Connection(
	new \CeusMedia\Database\PDO\DataSourceName( $dbDriver, $dbName ),
	$dbUsername, $dbPassword
);
```
### Tables

Existing database tables can be declared as tables:

#### Table class

```php
class MyFirstTable extends \CeusMedia\Database\PDO\Table{
	protected $name				= "my_first_table";
	protected $columns			= array(
		'id',
		'maybeSomeForeignId',
		'content',
		'timestamp',
	);
	protected $primaryKey		= 'id';
	protected $indices			= array(
		'maybeSomeForeignId',
	);
	protected $fetchMode		= \PDO::FETCH_OBJ;
}
```
#### Table instance

Having this defined structure, you can use a table instance for reading from and writing into the database table. Hence that you need to create a database connection beforehand.

```php
$table	= new MyFirstTable( $dbc );
```
#### Reading an entry

Example for getting an entry by its primary key:

```php
$entry	= $table->get( 1 );
```
The result will be an object of table columns and their values, since the fetch mode is set to object-wise by table structure:

```php
object stdObject(
	'id'					=> 1,
	'maybeSomeForeignId'	=> 123,
	'content'				=> 'Content of first entry.'
)
```
Not having the fetch mode set would result in an associated array, which is set as default fetch mode in underlaying table reader. To change the fetch see below.

**Hint:** There are more methods to retrive a single entry:

- getByIndex
- getByIndices

which allow to focus on foreign indices instead of the primary key.

#### Finding entries

A group of entries, filtered by a foreign key:

```php
$someEntries	= $table->getAllByIndex( 'maybeSomeForeignId', 123 );
```

A group of entries, filtered by several foreign keys:

```php
$someEntries	= $table->getAllByIndices( array(
	'maybeSomeForeignId'	=> 123,
	'notExistingKey'		=> 'will result in an exception',
);
```
To get **all entries**, call:

```php
$allEntries	= $table->getAll();
```
which may be bad in scaling, so reduce the result set by defining limits and conditions:

```php
$conditions	= array( 'content' => '%test%' );
$orders		= array();
$limits		= array( $offset = 0, $limit = 10 );

$allEntries	= $table->getAll( $conditions, $orders, $limits );
```
Conditions can be indices or any other column.

Orders are pairs of columns and directions, like:

```php
$orders	= array(
	'maybeSomeForeignId'	=> 'DESC',
	'content'				=> 'ASC',
);
```
There are more parameters possible for each of this indexing methods, which allow:

- $fields: restricting columns in result set
- grouping: apply GROUP BY
- having: apply HAVING


#### Change fetch mode

In your table structure class, set:

```php
	protected $fetchMode			= \PDO::[YOUR_FETCH_MODE];
```
where YOUR_FETCH_MODE is one of these standard PDO fetch modes:

- FETCH_ASSOC
- FETCH_NAMED
- FETCH_NUM
- FETCH_BOTH
- FETCH_OBJ
