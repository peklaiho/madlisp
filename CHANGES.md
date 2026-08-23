# Changes in MadLisp 2.0

MadLisp 2.0 is a new version of the language which features a Compiler that produces intermediate representation (IR) code. It also has an Executor which takes the IR and executes it. The compiler currently supports a limited subset of the full language. It is intended to provide better performance at the cost of some extended functionality. The user is responsible for choosing whether to invoke the compiled or the evaluated version of the language depending on what functionality their program requires.

This document details the features that are not implemented yet for the compiled language, as well as other changes and limitations.

## Macros

Macros are not yet supported in the compiled version, but there are plans to add them.

## Missing special forms

The following special forms are not yet supported by the compiler:

- eval
- macro
- macroexpand
- meta
- quasiquote
- quasiquote-expand
- try

## New special forms and core functions

- `compile` is a new core function for invoking the compiler
- `execute` is a new special form for executing compiled programs

## Def and immutable local variables

In the compiled version local variables cannot be set with the `def` form, so `def` always sets global variables.

Currently local variables are immutable, so there is no other way to set them either, except during initialization.

## Static core functions

Some key core functions are statically defined and cannot be redefined globally using `def`.

Though they can still be shadowed locally by using `let`:

```
(let (+ custom-fn)
  (+ 1 2))
```

## Misc changes

- Evaluating empty list () is an error by the compiler (in evaluated version empty list returns itself)
- Functions with variadic parameters using the & symbol are not supported yet
- Special constants `__FILE__` and `__DIR__` are not supported (mostly used with `load`)
- Functions `min` and `max` work with a single argument that is not a sequence
