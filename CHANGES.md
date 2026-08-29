# Changes in MadLisp 2.0

## PHP Compiler

MadLisp 2.0 includes a `PhpCompiler` which compiles Lisp code into PHP code. Compiled programs execute much faster (up to 50x faster) but intentionally only support a specific subset of the language.

If performance is important, you should use the compiled version of the language and work around the limitations that it has.

On the other hand, if you need more dynamic features of the language, and are working with a situation where performance is not so critical, use the evaluated version of the language.

### Performance optimizations

Local variables defined using `let` or `fn` are emitted as regular local PHP variables and they do not create a new environment (`Env` instance). This is important for performance because it reduces the need to perform environment lookups. But note that `def` and `undef` operate only on the current environment so they cannot be used to manipulate local variables.

User functions are compiled directly into native PHP `Closure` objects and are not wrapped in `UserFunc` anymore. This prevents some features such as setting or reading docstrings for user-defined functions.

The compiler emits some commonly used functions directly as raw PHP code instead of calling the `CoreFunc` instance. This allows basic math operations and comparison operators to be executed directly as PHP operators without generating function calls.

This fast-path execution exists for the following functions:

* Basic math operations: `+`, `-`, `*`, `/`, `//`, `%`
* Other math and logic: `inc`, `dec`, `not`
* Comparison operations: `=`, `==`, `!=`, `!==`, `<`, `<=`, `>`, `>=`
* Predicates: `zero?`, `one?`, `even?`, `odd?`

Note that comparison functions emit raw PHP comparison operators and do not use `Util::valueForCompare`.

Trying to redefine these fast-path functions is not supported.

Additionally, there may be slight differences between the fast-path implementations and the corresponding `CoreFunc` implementations.

### Unsupported features in compiler

Macros are not yet supported by the compiler. Thus the following built-in macros are also not supported:

* `defn`
* `defmacro`
* `when`
* `unless`

The following special forms are not supported by the compiler:

* `eval`
* `load`
* `macro`
* `macroexpand`
* `meta`
* `quasiquote`
* `quasiquote-expand`

Other features that are not supported by compiled programs:

* The `case` and `case-strict` forms do not call `Util::valueForCompare` limiting their functionality for comparing symbols or collections
* User-defined functions with variadic parameters using the `&` symbol

## Library changes

* Functions `empty?`, `len` and `reverse` only work on collections. Separate functions `strlen` and `strrev` have been added for strings.
