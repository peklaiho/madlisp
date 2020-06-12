# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter written in PHP. It is inspired by the [Make a Lisp](https://github.com/kanaka/mal) project, but does not follow that convention or syntax strictly.

## Features

The implementation is pretty minimalistic, but there is a good collection of built-in functions. Also [tail call optimization](https://en.wikipedia.org/wiki/Tail_call) is included.

## Requirements

The project requires PHP 7.4 or newer.

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

## Types

### Numbers

Numeric literals are interpreted as integer or floating point values. For example `1` or `1.0`.

### Strings

Strings are limited by double quotes, for example `"this is a string"`.

### Comments

Comments start with semicolon `;` and end on a newline character.

### Keywords

Special keywords are `true`, `false` and `null` which correspond to same PHP values.

### Sequences

Lists are limited by parenthesis. When they are evaluated, the first item of a list is called as a function with the remaining items as arguments. They can be defined using the built-in `list` function:

```
> (list 1 2 3)
(1 2 3)
```

Vectors are defined using square brackets or the built-in `vector` function:

```
> [1 2 3]
[1 2 3]

(vector 4 5 6)
[4 5 6]
```

Internally lists and vectors are just PHP arrays, and the only difference is how they are evaluated.

### Hash maps

Hash maps are collections of key-value pairs. Keys are normal strings, not "keywords" starting with colon characters as in many Lisp languages.

Hash maps are defined using curly brackets or using the built-in `hash` function. Odd arguments are treated as keys and even arguments are treated as values. The key-value pair can optionally include colon as a separator to make it more readable, but it is ignored internally.

```
> (hash "a" 1 "b" 2)
{"a":1 "b":2}

> {"key":"value"}
{"key":"value"}
```

Internally hash maps are just regular associative PHP arrays.

### Symbols

Symbols are words which do not match any other type and are separated by whitespace. They can contain special characters. Examples of symbols are `a`, `name` or `+`.

## Quoting

The special single quote character can be used to quote an expression (skip evaluation).

```
> '(1 2 3)
(1 2 3)
```

## Special forms

Name  | Example | Example result | Description
----- | ------- | -------------- | -----------
and   | `(and 1 0 2)` | `0` | Return the first value that is false, or the last value.
case  | `(case (= 1 0) 0 (= 1 1) 1)` | `1` | Treat odd arguments as tests and even arguments as values. Evaluate and return the value after the first test that evalutes to true.
      | `(case (= 1 0) 0 1)` | `1` | You can also give optional last argument to case which is returned if none of the tests evaluated to true.
def   | `(def addOne (fn (a) (+ a 1)))` | `<function>` | Define a value in the current environment.
do    | `(do (print 1) 2)` | `12` | Evaluate multiple expressions and return the value of the last.
env   | `(env +)` | `<function>` | Return a definition from the current environment represented by argument. Without arguments return the current environment as a hash-map.
eval  | `(eval (quote (+ 1 2)))` | `3` | Evaluate the argument.
fn    | `(fn (a b) (+ a b))` | `<function>` | Create a function.
if    |
let
load
or
quote |

## Core functions

Name  | Example | Description
----- | ------- | -----------
doc   | `(doc +)` | Show description of a built-in function.
read  | `(read "(+ 1 2 3)")` | Read a string as code and return the expression.
print | `(print "hello world")` | Print expression on the screen.
error | `(error "invalid value")` | Throw an exception with message as argument.

## License

[MIT](https://choosealicense.com/licenses/mit/)
