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
	protected $name			= "my_first_table";
	protected $columns		= array(
		'id',
		'maybeSomeForeignId',
		'content',
	);
	protected $primaryKey		= 'id';
	protected $indices		= array(
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
$indices		= array(
	'maybeSomeForeignId'	=> 123,
	'notExistingKey'		=> 'will result in an exception',
);
$someEntries	= $table->getAllByIndices( $indices );
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
	'content'		=> 'ASC',
);
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
$number	= $table->countByIndices( array(
	'maybeSomeForeignId'	=> 123,
	'notExistingKey'		=> 'will result in an exception',
);
```
To get **all entries**, call:

```php
$number	= $table->count();
```
which may be bad in scaling, so reduce the result set by defining conditions:

```php
$Conditions	= array(
	'maybeSomeForeignId'	=> 123,
	'content'		=> '%test%',
);
$number	= $table->count( $conditions );
```
**Hint:** Counting having really large MySQL tables may be slow.
There is a method to count in large tables in a faster way. You will find it.

#### Adding an entry

```php
$data		= array(
	'maybeSomeForeignId'	=> 123,
	'content'				=> 'Second entry.',
);
$entryId	= $table->add( $data );
```
**Attention:** For security reasons, all HTML tags will be striped. Set second parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!


#### Updating an entry

```php
$primaryKey	= 2;
$data		= array(
	'maybeSomeForeignId'	=> 124,
	'content'				=> 'Second entry - changed.',
);
$result	= $table->edit( $primaryKey, $data );
```
where the result will be the number of changed entries.

**Attention:** For security reasons, all HTML tags will be striped. Set third parameter to FALSE to avoid that, if needed. Make sure to strip HTML tags of none-HTML columns manually!

#### Updating several entries

```php
$indices	= array(
	'maybeSomeForeignId'	=> 123,
);
$data		= array(
	'maybeSomeForeignId'	=> 124,
);
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
$indices	= array(
	'maybeSomeForeignId'	=> 123,
);
$result	= $table->removeByIndices( $indices );
```
where the result will be the number of removed entries.

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
