# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter and Lisp-to-PHP compiler written in PHP.

MadLisp 2.0 includes a compiler that generates PHP closures for a performance-oriented subset of the language. The evaluated and compiled versions have different capabilities. See the [changes](./CHANGES.md) for details.

## Requirements

The project requires PHP 8.3 or newer and [Composer](https://getcomposer.org/).

## Quickstart

Create a new directory and require the project using composer:

```text
$ mkdir mylisp
$ cd mylisp
$ composer require peklaiho/madlisp
```

Use the `vendor/bin/madlisp` executable to start the interpreter. Start the REPL with the `-r` option:

```text
$ vendor/bin/madlisp -r
>
```

You can evaluate Lisp code interactively inside the REPL:

```text
> (+ 1 2 3)
6
```

Alternatively you can evaluate a file that contains Lisp code (with optional `-p` to print out the result):

```text
$ echo "(+ 1 2 3)" > mylisp.mad
$ vendor/bin/madlisp -p mylisp.mad
6
```

Use `-c` to run code in compiled mode. Add `-s` to display the generated PHP source.

## Documentation

The full [documentation](https://madlisp.com/) is available on the project website.

## License

[MIT](./LICENSE)
