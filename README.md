# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter written in PHP.

## Requirements

The project requires PHP 7.4 or newer and [Composer](https://getcomposer.org/).

## Quickstart

Create a new directory and require the project using composer:

```text
$ mkdir mylisp
$ cd mylisp
$ composer require "maddy83/madlisp dev-master"
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

Alternatively you can evaluate a file that contains Lisp code:

```text
$ echo "(+ 1 2 3)" > mylisp.mad
$ vendor/bin/madlisp mylisp.mad
6
```

## Documentation

The full [documentation](http://madlisp.com/) is available on the project website.

## License

[MIT](https://bitbucket.org/maddy83/madlisp/src/master/LICENSE)
