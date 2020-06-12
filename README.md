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

> (vector 4 5 6)
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
and   | `(and 1 0 2)` | `0` | Return the first value that evaluates to false, or the last value.
case  | `(case (= 1 0) 0 (= 1 1) 1)` | `1` | Treat odd arguments as tests and even arguments as values. Evaluate and return the value after the first test that evaluates to true.
      | `(case (= 1 0) 0 "no match")` | `"no match"` | You can also give optional last argument to case which is returned if none of the tests evaluated to true.
def   | `(def addOne (fn (a) (+ a 1)))` | `<function>` | Define a value in the current environment.
do    | `(do (print 1) 2)` | `12` | Evaluate multiple expressions and return the value of the last.
env   | `(env +)` | `<function>` | Return a definition from the current environment represented by argument. Without arguments return the current environment as a hash-map.
eval  | `(eval (quote (+ 1 2)))` | `3` | Evaluate the argument.
fn    | `(fn (a b) (+ a b))` | `<function>` | Create a function.
if    | `(if (< 1 2) "yes" "no")` | If the first argument evaluates to true, evaluate and return the second argument, otherwise the third argument. If the third argument is omitted `null` in its place.
let   | `(let (a (+ 1 2)) a)` | `3` | Create a new local environment using the first argument (list) to define values. Odd arguments are treated as keys and even arguments are treated as value. The last argument is the body of the let-expression which is evaluated in this new environment.
load  | `(load "file.mad")` | | Read and evaluate a file. The contents are implicitly wrapped in a do expression.
or    | `(or false 0 1)` | `1` | Return the first value that evaluates to true, or the last value.
quote | `(quote (1 2 3))` | `(1 2 3)` | Return the argument without evaluation. This is same as the `'` shortcut described above.

## Functions

### Core

Name  | Example | Example result | Description
----- | ------- | -------------- | -----------
doc   | `(doc +)` | `"Return the sum of all arguments."` | Show description of a built-in function.
read  | `(read "(+ 1 2 3)")` | `(+ 1 2 3)` | Read a string as code and return the expression.
print | `(print "hello world")` | `"hello world"null` | Print expression on the screen. Print returns null (which is shown due to the extra print in repl).
error | `(error "invalid value")` | `error: invalid value` | Throw an exception with message as argument.

### Collections

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
hash    | `(hash "a" 1 "b" 2)` | `{"a":1 "b":2}` | Create a new hash-map.
list    | `(list 1 2 3)` | `(1 2 3)` | Create a new list.
vector  | `(vector 1 2 3)` | `[1 2 3]` | Create a new vector.
range   | `(range 2 5)` | `[2 3 4]` | Create a vector with integer values from first to argument (inclusive) to second argument (exclusive).
range   | `(range 5)` | `[0 1 2 3 4]` | Range can also be used with one argument in which case it is used as length for a vector of integers starting from 0.
empty?  | `(empty? [])` | `true` | Return true if collection is empty, otherwise false.
get     | `(get [1 2 3] 0)` | `1` | Return the nth element from a sequence, or the corresponding value for the given key from a hash-map.
len     | `(len [1 2 3])` | `3` | Return the number of elements in a collection.
len     | `(len "hello world")` | `11` | Return the length of a string using [strlen](https://www.php.net/manual/en/function.strlen.php).
first   | `(first [1 2 3 4])` | `1` | Return the first element of a sequence.
second  | `(second [1 2 3 4])` | `2` | Return the second element of a sequence.
penult  | `(penult [1 2 3 4])` | `3` | Return the second-last element of a sequence.
last    | `(last [1 2 3 4])` | `4` | Return the last element of a sequence.
head    | `(head [1 2 3 4])` | `[1 2 3]` | Return new sequence which contains all elements except the last.
tail    | `(tail [1 2 3 4])` | `[2 3 4]` | Return new sequence which contains all elements except the first.
apply   | `(apply + 1 2 [3 4])` | `10` | Call the first argument using a sequence as argument list. Intervening arguments are prepended to the list.
chunk   | `(chunk [1 2 3 4 5] 2)` | `[[1 2] [3 4] [5]]` | Divide a sequence to multiple sequences with specified length using [array_chunk](https://www.php.net/manual/en/function.array-chunk.php).
push    | `(push [1 2] 3 4)` | `[1 2 3 4]` | Create new sequence by inserting arguments at the end.
pull    | `(pull 1 2 [3 4])` | `[1 2 3 4]` | Create new sequence by inserting arguments at the beginning.
map     | `(map (fn (a) (* a 2)) [1 2 3])` | `[2 4 6]` | Create new sequence by calling a function for each item. Uses [array_map](https://www.php.net/manual/en/function.array-map.php) internally.
map2    | `(map2 + [1 2 3] [4 5 6])` | `[5 7 9]` | Create new sequence by calling a function for each item from both sequences.
reduce  | `(reduce + [2 3 4] 1)` | `10` | Reduce a sequence to a single value by calling a function sequentially of all arguments. Optional third argument is used to give the initial value for first iteration. Uses [array_reduce](https://www.php.net/manual/en/function.array-reduce.php) internally.
filter  | `(filter odd? [1 2 3 4 5])` | `[1 3 5]` | Create a new sequence by using the given function as a filter. Uses [array_filter](https://www.php.net/manual/en/function.array-filter.php) internally.
reverse | `(reverse [1 2 3])` | `[3 2 1]` | Reverse the order of a sequence. Uses [array_reverse](https://www.php.net/manual/en/function.array-reverse.php) internally.
key?    | `(key? {"a" "b"} "a")` | `true` | Return true if the hash-map contains the given key.
set     | `(set {"a" 1} "b" 2)` | `{"a":1 "b":2}` | Create new hash-map which contains the given key-value pair.
set!    | `(set! {"a" 1} "b" 2)` | `2` | Modify the given hash-map by setting the key-value pair and returning the set value. **This function is mutable!**
keys    | `(keys {"a" 1 "b" 2})` | `("a" "b")` | Return a list of the keys for a hash-map.
values  | `(values {"a" 1 "b" 2})` | `(1 2)` | Return a list of the values for a hash-map.
zip     | `(zip ["a" "b"] [1 2])` | `{"a":1 "b":2}` | Create a hash-map using the first sequence as keys and the second as values. Uses [array_combine](https://www.php.net/manual/en/function.array-combine.php) internally.
sort    | `(sort [6 4 8 1])` | `[1 4 6 8]` | Sort the sequence using [sort](https://www.php.net/manual/en/function.sort.php).

## Extending

The project is easy to extend because it is trivial to add new functions whether the implementation is defined on the PHP or Lisp side. If the language ends up being used in the future, first plans are to add support for JSON serialization and a HTTP client.

## Known issues

Special characters such as `\n` or `\r` are not handled/escaped correctly in strings.

## License

[MIT](https://choosealicense.com/licenses/mit/)
