# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter written in PHP. It is inspired by the [Make a Lisp](https://github.com/kanaka/mal) project, but does not follow that convention or syntax strictly.

## Goals

The goal of the project was to learn about the internals of programming languages and to build a simple language suitable for scripting and similar use cases.

## Features

The implementation is pretty minimalistic, but there is a good collection of built-in functions. Also [tail call optimization](https://en.wikipedia.org/wiki/Tail_call) is included.

## Requirements

The project requires PHP 7.4 or newer.

The core project does not have any dependencies to external [Composer](https://getcomposer.org/) libraries, but it does currently use Composer for the autoloader so you need to run **composer install** for that.

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

Internally lists and vectors are just PHP arrays wrapped in a class, and the only difference between the two is how they are evaluated. Another reason for adding vectors is the familiarity of the square bracket syntax for PHP developers. They can be thought of as PHP arrays for most intents and purposes.

### Hash maps

Hash maps are collections of key-value pairs. Keys are normal strings, not "keywords" starting with colon characters as in many Lisp languages.

Hash maps are defined using curly brackets or using the built-in `hash` function. Odd arguments are treated as keys and even arguments are treated as values. The key-value pair can optionally include colon as a separator to make it more readable, but it is ignored internally.

```
> (hash "a" 1 "b" 2)
{"a":1 "b":2}

> {"key":"value"}
{"key":"value"}
```

Internally hash maps are just regular associative PHP arrays wrapped in a class.

### Symbols

Symbols are words which do not match any other type and are separated by whitespace. They can contain special characters. Examples of symbols are `a`, `name` or `+`.

## Quoting

The special single quote character can be used to quote an expression (skip evaluation).

```
> '(1 2 3)
(1 2 3)
```

## Environments

Environments are hash-maps which store key-value pairs and use symbols as keys. Symbols are evaluated by looking up the corresponding value from the current environment. If the key is not defined in current environment the lookup proceeds to the parent environment and so forth. The initial environment is called `root` and contains all the built-in functions listed here. Then another environment called `user` is created for anything the user wants to define. The `let` and `fn` special forms create new local environments. Note that `def` always uses the current environment, so anything defined with `def` is not visible in the parent environment.

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
if    | `(if (< 1 2) "yes" "no")` | `"yes"` | If the first argument evaluates to true, evaluate and return the second argument, otherwise the third argument. If the third argument is omitted return `null` in its place.
let   | `(let (a (+ 1 2)) a)` | `3` | Create a new local environment using the first argument (list) to define values. Odd arguments are treated as keys and even arguments are treated as values. The last argument is the body of the let-expression which is evaluated using this new environment.
load  | `(load "file.mad")` | | Read and evaluate a file. The contents are implicitly wrapped in a `do` expression.
or    | `(or false 0 1)` | `1` | Return the first value that evaluates to true, or the last value.
quote | `(quote (1 2 3))` | `(1 2 3)` | Return the argument without evaluation. This is same as the `'` shortcut described above.

## Functions

### Core functions

Name  | Example | Example result | Description
----- | ------- | -------------- | -----------
doc   | `(doc +)` | `"Return the sum of all arguments."` | Show description of a built-in function.
read  | `(read "(+ 1 2 3)")` | `(+ 1 2 3)` | Read a string as code and return the expression.
print | `(print "hello world")` | `"hello world"null` | Print expression on the screen. Print returns null (which is shown due to the extra print in repl).
error | `(error "invalid value")` | `error: invalid value` | Throw an exception with message as argument.

### Collection functions

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
set!    | `(set! {"a" 1} "b" 2)` | `2` | Modify the given hash-map by setting the given key-value pair and return the set value. **This function is mutable!**
keys    | `(keys {"a" 1 "b" 2})` | `("a" "b")` | Return a list of the keys for a hash-map.
values  | `(values {"a" 1 "b" 2})` | `(1 2)` | Return a list of the values for a hash-map.
zip     | `(zip ["a" "b"] [1 2])` | `{"a":1 "b":2}` | Create a hash-map using the first sequence as keys and the second as values. Uses [array_combine](https://www.php.net/manual/en/function.array-combine.php) internally.
sort    | `(sort [6 4 8 1])` | `[1 4 6 8]` | Sort the sequence using [sort](https://www.php.net/manual/en/function.sort.php).

### Comparison functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
`=`     | `(= 1 "1")` | `true` | Compare arguments for equality using the `==` operator in PHP.
`==`    | `(== 1 "1")` | `false` | Compare arguments for strict equality using the `===` operator in PHP.
`!=`    | `(!= 1 "1")` | `false` | Compare arguments for not-equality using the `!=` operator in PHP.
`!==`   | `(!== 1 "1")` | `true` | Compare arguments for strict not-equality using the `!==` operator in PHP.
`<`     | `(< 1 2)` | `true` | Return true if first argument is less than second.
`<=`    | `(<= 1 2)` | `true` | Return true if first argument is less or equal to second.
`>`     | `(> 1 2)` | `false` | Return true if first argument is greater than second.
`>=`    | `(>= 1 2)` | `false` | Return true if first argument is greater or equal to second.

### IO functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
file?   | `(file? "test.txt")` | `true` | Return true if the file exists.
fread   | `(fread "test.txt")` | `"content"` | Read the contents of a file.
fwrite  | `(fwrite "test.txt" "content")` | `true` | Write string to file. Give optional third parameter as `true` to append.

### Math functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
`+`   | `(+ 1 2 3)` | `6` | Return the sum of the arguments.
`-`   | `(- 4 2 1)` | `1` | Subtract the other arguments from the first.
`*`   | `(* 2 3 4)` | `24` | Multiply the arguments.
`/`   | `(/ 7 2)` | `3.5` | Divide the arguments.
`//`  | `(// 7 2)` | `3` | Divide the arguments using integer division.
`%`   | `(% 6 4)` | `2` | Calculate the modulo.
inc   | `(inc 1)` | `2` | Increment the argument by one.
dec   | `(dec 2)` | `1` | Decrement the argument by one.
sin   | `(sin 1)` | `0.84` | Calculate the sine.
cos   | `(cos 1)` | `0.54` | Calculate the cosine.
tan   | `(tan 1)` | `1.55` | Calculate the tangent.
abs   | `(abs -2)` | `2` | Get the absolute value.
floor | `(floor 2.5)` | `2` | Get the next lowest integer.
ceil  | `(ceil 2.5)` | `3` | Get the next highest integer.
pow   | `(pow 2 4)` | `16` | Raise the first argument to the power of the second argument.
sqrt  | `(sqrt 2)` | `1.41` | Calculate the square root.

### String functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
len     | `(len "hello world")` | `11` | Return the length of a string using [strlen](https://www.php.net/manual/en/function.strlen.php).
trim    | `(trim " abc ")` | `"abc"` | Trim the string using [trim](https://www.php.net/manual/en/function.trim).
upcase  | `(upcase "abc")` | `"ABC"` | Make the string upper case using [strtoupper](https://www.php.net/manual/en/function.strtoupper).
lowcase | `(lowcase "Abc")` | `"abc"` | Make the string lower case using [strtolower](https://www.php.net/manual/en/function.strtolower.php).
substr  | `(substr "hello world" 3 5)` | `"lo wo"` | Get a substring using [substr](https://www.php.net/manual/en/function.substr.php).
replace | `(replace "hello world" "hello" "bye")` | `"bye world"` | Replace substrings using [str_replace](https://www.php.net/manual/en/function.str-replace.php).
split   | `(split "-" "a-b-c")` | `("a" "b" "c")` | Split string using [explode](https://www.php.net/manual/en/function.explode.php).
join    | `(join "-" "a" "b" "c")` | `"a-b-c"` | Join string together using [implode](https://www.php.net/manual/en/function.implode.php).
format  | `(format "%d %.2f" 56 4.5)` | `"56 4.50"` | Format string using [sprintf](https://www.php.net/manual/en/function.sprintf.php).

Note that support for multibyte characters in strings is limited because the provided functions do not use the [mbstring](https://www.php.net/manual/en/book.mbstring.php) extension.

### Time functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
time | `(time)` | `1592011969` | Return the current unix timestamp using [time](https://www.php.net/manual/en/function.time).
date | `(date "Y-m-d H:i:s")` | `"2020-06-13 08:33:29"` | Format the current time and date using [date](https://www.php.net/manual/en/function.date.php).
strtotime | `(strtotime "2020-06-13 08:34:47")` | `1592012087` | Parse datetime string into unix timestamp using [strtotime](https://www.php.net/manual/en/function.strtotime.php).
sleep | `(sleep 2000)` | `null` | Sleep for the given period given in milliseconds using [usleep](https://www.php.net/manual/en/function.usleep).

### Type functions

Skipped examples here as these are pretty self-explanatory.

Name    | Description
------- | -----------
bool | Convert the argument to boolean.
float | Convert the argument to floating-point value.
int | Convert the argument to integer.
str | Convert the argument to string. Also concatenate multiple strings together.
symbol | Convert the argument to symbol.
not | Turns true to false and vice versa.
type | Return the type of the argument as a string.
fn? | Return true if the argument is a function.
list? | Return true if the argument is a list.
vector? | Return true if the argument is a vector.
seq? | Return true if the argument is a sequence (list or vector).
hash? | Return true if the argument is a hash-map.
symbol? | Return true if the argument is a symbol.
bool? | Return true if the argument is a boolean value (strict comparison).
true? | Return true if the argument evaluates to true (non-strict comparison).
false? | Return true if the argument evaluates to false (non-strict comparison).
null? | Return true if the argument is null (strict comparison).
int? | Return true if the argument is an integer.
float? | Return true if the argument is a floating-point value.
str? | Return true if the argument is a string.
zero? | Return true if the argument is integer 0 (strict comparison).
one? | Return true if the argument is integer 1 (strict comparison).
even? | Return true if the argument is even number (0, 2, 4, ...).
odd? | Return true if the argument is odd number (1, 3, 5, ...).

## Extending

The project is easy to extend because it is trivial to add new functions whether the implementation is defined on the PHP or Lisp side. If the language ends up being used in the future, first plans are to add support for JSON serialization and a HTTP client.

## Known issues

Special characters such as `\"`, `\n` or `\r` are not handled/escaped correctly in strings yet.

## License

[MIT](https://choosealicense.com/licenses/mit/)