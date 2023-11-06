# Database

![Branch](https://img.shields.io/badge/Branch-0.6.x-blue?style=flat-square)
![Release](https://img.shields.io/badge/Release-0.6.0-blue?style=flat-square)
![PHP version](https://img.shields.io/badge/PHP-%5E8.1-blue?style=flat-square&color=777BB4)
![PHPStan level](https://img.shields.io/badge/PHPStan_level-max+strict-darkgreen?style=flat-square)

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
class MyFirstTable extends \CeusMedia\Database\PDO\Table
{
	protected string $name			= "my_first_table";
	protected array $columns		= [
		'id',
		'maybeSomeForeignId',
		'content',
	];
	protected string $primaryKey	= 'id';
	protected array $indices		= [
		'maybeSomeForeignId',
	];
	protected int $fetchMode		= \PDO::FETCH_OBJ;
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

**Hint:** There are more methods to read a single entry:

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
$indices		= [
	'maybeSomeForeignId'	=> 123,
	'notExistingKey'		=> 'will result in an exception',
];
$someEntries	= $table->getAllByIndices( $indices );
```
To get **all entries**, call:

```php
$allEntries	= $table->getAll();
```
which may be bad in scaling, so reduce the result set by defining limits and conditions:

```php
$conditions	= ['content' => '%test%'];
$orders		= [];
$limits		= [$offset = 0, $limit = 10];

$allEntries	= $table->getAll( $conditions, $orders, $limits );
```
Conditions can be indices or any other column.

Orders are pairs of columns and directions, like:

```php
$orders	= [
	'maybeSomeForeignId'	=> 'DESC',
	'content'		=> 'ASC',
];
```
There are more parameters possible for each of this indexing methods, which allow:

- fields: restricting columns in result set
- grouping: apply GROUP BY
- having: apply HAVING

#### Counting

To count entries by a foreign key:

```php
$number	= $table->countByIndex( 'maybeSomeForeignId', 123 );
```

To count entries, filtered by several foreign keys:

```php
$number	= $table->countByIndices( [
	'maybeSomeForeignId'	=> 123,
	'notExistingKey'		=> 'will result in an exception',
] );
```
To get **all entries**, call:

```php
$number	= $table->count();
```
which may be bad in scaling, so reduce the result set by defining conditions:

```php
$Conditions	= [
	'maybeSomeForeignId'	=> 123,
	'content'		=> '%test%',
];
$number	= $table->count( $conditions );
```
**Hint:** Counting having really large MySQL tables may be slow.
There is a method to count in large tables in a faster way. You will find it.

#### Adding an entry

```php
$data		= [
	'maybeSomeForeignId'	=> 123,
	'content'				=> 'Second entry.',
];
$entryId	= $table->add( $data );
```
**Attention:** For security reasons, all HTML tags will be striped. Set second parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!


#### Updating an entry

```php
$primaryKey	= 2;
$data		= [
	'maybeSomeForeignId'	=> 124,
	'content'				=> 'Second entry - changed.',
];
$result	= $table->edit( $primaryKey, $data );
```
where the result will be the number of changed entries.

**Attention:** For security reasons, all HTML tags will be striped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

#### Updating several entries

```php
$indices	= [
	'maybeSomeForeignId'	=> 123,
];
$data		= [
	'maybeSomeForeignId'	=> 124,
];
$result	= $table->editByIndices( $indices, $data );
```
where the result will be the number of changed entries.

**Attention:** For security reasons, all HTML tags will be striped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

#### Removing an entry

```php
$primaryKey	= 2;
$result	= $table->remove( $primaryKey );
```
where the result will be the number of removed entries.

#### Removing several entry

```php
$indices	= [
	'maybeSomeForeignId'	=> 123,
];
$result	= $table->removeByIndices( $indices );
```
where the result will be the number of removed entries.

#### Change fetch mode

In your table structure class, set:

```php
	protected int $fetchMode		= \PDO::[YOUR_FETCH_MODE];
```
where YOUR_FETCH_MODE is one of these standard PDO fetch modes:

- FETCH_ASSOC
- FETCH_NAMED
- FETCH_NUM
- FETCH_BOTH
- FETCH_OBJ

### Entities

Reading from tables can return lists of arrays or anonymous objects, easily.  
To use entity classes to receive data objects, PDO's fetch mode can be set to <code>FETCH_CLASS</code>.
A table implementation needs to set <code>::fetchEntityClass</code> to a class name.

This could be an entity class:
```
class MyFirstTableEntity
{
    public string $id;
    public string $maybeSomeForeignId;
    public string $content;
}
```
This entity class can be linked within the table as class to use on fetch:
```
class MyFirstTable extends Table
{
    ...
    public ?string $fetchEntityClass    = '\\MyProject\\MyFirstTableEntity';
}
```
Now, all indexing methods will return lists of filled entity classes. 

## Code Examples using OSQL

Having a config file like this:
```php
driver		= 'mysql';
host		= 'myHost';
port		= 'myPort';
database	= 'myDatabase';
username	= 'myDatabaseUser';
password	= 'myDatabasePassword';
```
and assuming that you load things up like this:
```php
require_once 'vendor/autoload.php';

use CeusMedia\Database\PDO\DataSourceName;
use CeusMedia\Database\OSQL\Client;
use CeusMedia\Database\OSQL\Connection;
use CeusMedia\Database\OSQL\Condition;
use CeusMedia\Database\OSQL\Table;
use CeusMedia\Database\OSQL\Query\Select;

$config	= (object) parse_ini_file( 'myConfigFile.ini' );
```
you can connect to a database like this:
```php
$client	= new Client( new Connection( DataSourceName::renderStatic(
	$config->driver,
	$config->database,
	$config->host,
	$config->port,
	$config->username,
	$config->password
), $config->username, $config->password ) );
```
Now you can query the database like this:
```php
$result	= Select::create( $client )
	->from( new Table( 'galleries', 'g' ) )
	->where( new Condition( 'galleryId', 1, Condition::OP_EQ ) )
	->execute();
```
The result will contain the requested rows (only one in this example):
```php
new UI_DevOutput();
print_m( $result );
```
will produce:

```php
[O] 0 -> stdClass
   [S] galleryId => 1
   [S] status => 0
   [S] rank => 1
   [S] path => test
   [S] title => Test
   [S] description => Das ist ein Test.
   [S] timestamp => 1402008611
   ```
