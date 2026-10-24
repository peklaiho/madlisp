# Changes in MadLisp 2.0

MadLisp 2.0 includes a `PhpCompiler` which compiles Lisp code into PHP code. Compiled programs execute much faster but intentionally support only a specific subset of the language.

If performance is important, you should use the compiled version of the language and work around the limitations that it has.

On the other hand, if you need more dynamic features of the language and performance is not so critical, use the evaluated version of the language.

## Performance optimizations

Local variables defined using `let` or `fn` are emitted as regular local PHP variables and they do not create a new environment (`Env` instance). This is important for performance because it reduces the need to perform environment lookups. But note that `def` and `undef` operate only on the current environment so they cannot be used to manipulate local variables.

User functions are compiled directly into native PHP `Closure` objects and are not wrapped in `UserFunc` anymore. This prevents some features such as setting or reading docstrings for user-defined functions.

The compiler emits some commonly used functions directly as raw PHP code instead of calling the `CoreFunc` instance. This allows basic math operations and comparison operators to be executed directly as PHP operators without generating function calls.

This fast-path execution exists for the following functions:

* Basic math operations: `+`, `-`, `*`, `/`, `//`, `%`
* Other math and logic: `inc`, `dec`, `not`, `abs`, `floor`, `ceil`, `pow`, `sqrt`
* Comparison operations: `=`, `==`, `!=`, `!==`, `<`, `<=`, `>`, `>=`
* Predicates: `zero?`, `one?`, `even?`, `odd?`
* Collections: `empty?`, `len`, `car`, `first`, `cdr`, `tail`, `cons`, `last`, `get`, `key?`
* Strings: `strlen`

## Comparisons

Comparisons using `=`, `==`, `!=`, `!==` and comparisons done by `case` and `case-strict` emit raw PHP comparison operators by default. Set `Options::$compileSimpleComparisons` to false to use `Util::valueForCompare` instead.

## Macros

`Lisp::compile()` expands macros available in the compilation environment before compilation. `PhpCompiler::compile()` accepts only expanded ASTs and does not process macro definitions or calls.

The default environment provides these compiler-relevant macros:

* `defn`
* `when`
* `unless`

## Unsupported special forms

The following special forms are not supported when passed directly to the compiler:

* `eval`
* `load`
* `macro`
* `macroexpand`
* `meta`
* `quasiquote`
* `quasiquote-expand`

## Other changes in compiled version

* Compiling an empty unquoted list is an error (in the evaluated version empty list returns itself)
* User-defined functions have fixed arity; variadic parameters using the `&` symbol are not supported
* Integer division (`//`) and comparison operators require exactly two arguments
* Named-let calls must be in tail position and compile to labeled goto restarts

## Library changes

* Functions `empty?`, `len` and `reverse` only work on collections, not strings
* New functions `strlen` and `strrev` have been added for strings
