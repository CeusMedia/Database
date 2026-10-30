# Writing

## Adding an entry

```php
$orderId	= $orders->add( [
	'customerId'	=> 12,
	'total'			=> 49.9,
	'note'			=> 'Ring twice',
] );
```

`add()` returns the ID of the new entry. Data can be an array, an anonymous object or an entity object.

- Only declared columns are written, other keys are ignored.
- An auto-incremented primary key in the data is ignored.
- [Generated columns](tables.md#generated-columns) are never written.
- `NULL` values are left out, so the column gets its default value. This way an entity with
  properties preset to `null` can be added to a table with `NOT NULL` columns having defaults.
  To store `NULL` in a column with another default, set it by `edit()` afterward.
- Arrays and objects are written as JSON, `DateTime` objects as date string –
  see [JSON and DateTime columns](json-datetime.md).

!!! warning "HTML tags are stripped"
    For security reasons, HTML tags are stripped from all string values by default – on
    `add()`, `edit()`, `editByIndices()` and `save()`.
    Set the `$stripTags` parameter to `FALSE` to keep them, eg. for HTML content –
    and remember to strip tags of non-HTML columns yourself then.

    `strip_tags()` also cuts text that only looks like a tag: `'a<b'` is stored as `'a'`,
    without notice. For plain text that may contain `<` (formulas, code, messages), disable it
    and escape on output instead.

## Updating an entry

```php
$changed	= $orders->edit( $orderId, [
	'status'	=> 1,
	'note'		=> 'Ring twice, then knock',
] );
```

Returns the number of changed entries – `0`, if the entry does not exist or nothing changed.
Unlike on insert, `NULL` values set the column to `NULL`. HTML tags are stripped by default,
the third parameter disables that.

## Updating several entries

```php
$changed	= $orders->editByIndices( ['customerId' => 12, 'status' => 0], ['status' => 9] );
```

Like on `edit()`, `NULL` values set the column to `NULL`, and HTML tags are stripped by default –
the third parameter disables that.

Only declared indices (and the primary key) are accepted as filter – a typo can not turn into an
update of the whole table.

## Saving an entity

A fetched and changed entity can be written back directly, without building a data array:

```php
$order			= $orders->get( $orderId );
$order->note	= 'Changed';
$orders->save( $order );
```

`save()` reads the primary key from the entity and calls `edit()`. If the table has a configured
`fetchEntityClass` (or `fetchEntityObject`), the entity must be of that class.
See [Entities](entities.md).

## Removing entries

```php
$removed	= $orders->remove( $orderId );                        // TRUE, if removed
$number		= $orders->removeByIndex( 'customerId', 12 );
$number		= $orders->removeByIndices( ['customerId' => 12, 'status' => 9] );
```

As for updates, only declared indices are accepted as filter.

## Truncating a table

```php
$orders->truncate();
```

Removes all entries and resets the auto-increment counter. Unlike the other methods, it does not
return the number of removed entries. Cached entries of the table are removed from the [cache](caching.md).

!!! warning
    On MySQL, `TRUNCATE` commits an open transaction implicitly.
