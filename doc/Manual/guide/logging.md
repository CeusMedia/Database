# Logging and debugging

## Log files

```php
use CeusMedia\Database\PDO\Connection;

$dbc->setErrorLogFile( 'logs/db/error.log' );
$dbc->setStatementLogFile( 'logs/db/statement.log' );
$dbc->setLogLevel( Connection::LOG_LEVEL_ERROR | Connection::LOG_LEVEL_STATEMENT );
```

Available log levels (constants of [`Connection`](../api/PDO/Connection.md), combinable as bitmask):

| Level | Logs |
|-------|------|
| `LOG_LEVEL_NONE` | nothing |
| `LOG_LEVEL_ERROR` | failed statements, into the error log file (**default**) |
| `LOG_LEVEL_STATEMENT` | every statement, into the statement log file |

A log file must be set *and* the level must be enabled, for anything to be logged.
The format of error log entries can be changed by the static template `Connection::$errorTemplate`,
which knows the placeholders `{time}`, `{pdoCode}`, `{sqlCode}`, `{sqlError}`, `{message}` and `{statement}`.

## Logging a single statement

`setLogLevelForNextStatement()` overrides the log level for the next statement only –
it resets itself automatically afterward, even if the statement fails:

```php
$dbc->setStatementLogFile( 'logs/db/statement.log' );

$dbc->setLogLevelForNextStatement( Connection::LOG_LEVEL_STATEMENT );
$number	= $orders->count( ['status' => 0] );      // only this statement is logged
```

## Last query

```php
echo $orders->getLastQuery();     // SELECT COUNT(`orderId`) AS count FROM orders WHERE `status` = 0
echo $dbc->lastQuery;             // the same, read from the connection
```

`lastQuery` holds the latest statement given to `query()`, `prepare()` or `exec()` – so it
reflects every read and write method of a table. For statements prepared with placeholders,
these are not replaced by the bound values.

## Counting statements

The connection counts statements in `$dbc->numberStatements` (all prepared, queried and executed
statements) and `$dbc->numberExecutes` (`exec()` calls). Comparing them before and after a block
of code helps to find N+1 query problems:

```php
$before	= $dbc->numberStatements;
renderOrderList( $orders );
printf( '%d statements', $dbc->numberStatements - $before );
```
