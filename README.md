# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter written in PHP. It is inspired by the [Make a Lisp](https://github.com/kanaka/mal) project, but does not follow that convention or syntax strictly.

## Requirements

The project does use syntax of PHP 7.4 so that version or newer is required.

The project does not have any dependencies to external [Composer](https://getcomposer.org/) libraries, but it does use Composer for the autoloader so you need to run **composer install** for that.

## Usage

Use the **run.php** file to invoke the interpreter. You can invoke the Repl with the -r switch:

```
$ php run.php -r
```

You can also evaluate code directly with the -e switch:

```
$ php run.php -e "(+ 1 2 3)"
6
```

Or you can evaluate a file with the -f switch:

```
$ php run.php -f file.mad
```

## Special forms

## Core functions

Name  | Example | Description
----- | ------- | -----------
doc   | (doc +) | Show description of a built-in function.
read  | (read "(+ 1 2 3)") | Read a string as code and return the expression.
print | (print "hello world") | Print expression on the screen.
error | (error "invalid value") | Throw an exception with message as argument.

## License

[MIT](https://choosealicense.com/licenses/mit/)
