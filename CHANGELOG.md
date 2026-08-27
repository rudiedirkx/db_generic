Changelog
=========


2.x
---

New major version, with breaking API changes.

### Results and fetching

* `fetch()` and `select()` return a plain array of `stdClass`, not a result object.
* `fetch_by_field()` and `select_by_field()` return that array keyed by a column.
* The result object is gone from the public API: no more `->all()`, `->nextObject()` or iterating a result.
* `db_generic_result` still exists, but only internally.
* `fetch()` no longer takes an `$options` array (`params`, `class`); it takes a query string only.
* New: `fetch_first()` and `select_first()` return a single `?stdClass`.
* New: `fetch_assoc()`, `select_assoc()` and `select_assoc_first()` return rows as associative arrays.

### Errors

* Removed `open()`, `error()` and `errno()`.
* Every failure throws `db_exception`. Nothing returns `false` on error anymore.

### Writes

* Removed the `returnAffectedRows` flag.
* `execute()`, `insert()`, `inserts()`, `update()`, `delete()` and `replace()` now return `true`, and throw on failure.
* Use `affected_rows()` for the affected-row count.
* `Model::deleteAll()` and `Model::updateAll()` return the affected-row count as an `int`.
* `Model::insert()` returns `true` like the other write methods, instead of the new id (use `create()` for the object, or `insert_id()` for the id).

### Transactions

* `begin()`, `commit()` and `rollback()` return `true`.
* `db_sqlite` uses `PRAGMA journal_mode=MEMORY` instead of `OFF`, so `rollback()` works again.

### Models and relationships

* Removed `db_generic_record` and the `ArrayAccess` result wrapper.
* Raw rows are plain `stdClass`; extend `db_generic_model` for objects.
* `to_many_through()` now takes `($targetClass, $throughRelationship)` and reads the target ids from another relationship, instead of a join table and two columns.

### Drivers

* `db_mysql` is mysqli-only.
* MySQL-over-PDO moved to a new `db_mysql_pdo`.
* Both MySQL drivers share the schema builder via the `db_mysql_schema` trait.
* Added `db_pgsql` (PostgreSQL via PDO).

### Misc

* `query()` is now `protected`, so engine cursors (`mysqli_result` / `PDOStatement`) don't leak out of a driver.
* Drivers connect lazily and take their connection settings as constructor arguments (`open()` is gone).
* Requires PHP >= 8.3.
