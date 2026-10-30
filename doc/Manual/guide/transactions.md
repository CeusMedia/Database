# Transactions

The easiest way: give the work as callback to `transaction()`. It commits, if the callback
returns, and rolls back and rethrows, if it throws. The callback gets the connection and its
result is returned:

```php
$orderId	= $dbc->transaction( function() use ( $orders, $items, $order, $itemId, $changes ){
	$orderId	= $orders->add( $order );
	$items->edit( $itemId, $changes );
	return $orderId;
} );
```

This is the same as doing it by hand:

```php
$dbc->beginTransaction();
try{
	$orderId	= $orders->add( $order );
	$items->edit( $itemId, $changes );
	$dbc->commit();
}
catch( \Throwable $e ){
	$dbc->rollBack();
	throw $e;
}
```

## Nested transactions

Transactions can be nested safely:

- only the **outermost** `beginTransaction()` actually starts a database transaction,
- only the **outermost** `commit()` actually commits,
- if an **inner** `rollBack()` happened, the outermost `commit()` rolls back instead – and throws a
  `RuntimeException`, to make the failure visible instead of silently pretending success.

`getOpenTransactions()` returns the current nesting depth.

This allows every service method to open its own transaction. Called alone, it commits by
itself; called inside another transaction, it becomes part of it:

```php
class StockService
{
	public function reserve( array $items ): void
	{
		$this->dbc->transaction( function() use ( $items ){   // nested, if called by OrderService
			foreach( $items as $item )
				$this->stock->editByIndices( ['productId' => $item['productId']], ['reserved' => $item['quantity']] );
		} );                                                  // inner: commit only decreases the depth,
	}                                                         // an exception marks the whole transaction as failed
}

class OrderService
{
	public function placeOrder( array $order, array $items ): int
	{
		return $this->dbc->transaction( function() use ( $order, $items ){   // outermost: starts the transaction
			$orderId	= $this->orders->add( $order );
			$this->stockService->reserve( $items );
			return $orderId;
		} );                                                  // outermost: commits or rolls back everything
	}
}
```

If an inner callback fails but its exception is caught by the outer callback, the outer
transaction is still rolled back – and `transaction()` throws the `RuntimeException` of `commit()`.

!!! warning "Statements that end transactions"
    Some databases commit implicitly on schema changes (eg. MySQL on `CREATE`, `ALTER`, `DROP`
    and `TRUNCATE`). [`Table::truncate()`](../api/PDO/Table.md#truncate) is one of them –
    do not use it inside a transaction.

## Transactions left open

A transaction that is neither committed nor rolled back – eg. since an exception skipped both –
is rolled back by PDO, when the connection is closed. Its changes are lost silently.

To make this visible, the connection notes it in the [error log file](logging.md#log-files) on
destruction, if one is set and the log level contains `LOG_LEVEL_ERROR`:

```
1761750000: PDO: SQL: Transaction not closed, rolled back on closing connection (open transactions: 1)
```

There is no exception: it would hide the exception, which mostly is the reason of the open
transaction, and could not be handled on script shutdown anyway. Using `transaction()` avoids
open transactions in the first place.

