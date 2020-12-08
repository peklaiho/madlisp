# MadLisp

MadLisp is a [Lisp](https://en.wikipedia.org/wiki/Lisp_%28programming_language%29) interpreter written in PHP. It is inspired by the [Make a Lisp](https://github.com/kanaka/mal) project, but does not follow that convention or syntax strictly. It provides a fun platform for learning [functional programming](https://en.wikipedia.org/wiki/Functional_programming).

## Goals

* REPL environment where the user can interactively experiment with the language. Suitable for executing pieces of code one by one and examining the internal state of the system.
* Minimal safeguards or restrictions as to what can be done. Breaking things or using the language in unexpected ways should be part of the fun.
* Performance does not need to match commercial-grade languages, but needs to be fast enough for real-world programs and uses cases.
* Suitable to be used as a scripting language in Linux shell scripts and similar environments.
* Suitable to be used as an embedded scripting language inside another PHP application.
* Clear and intuitive error messages. This is important for pleasant user experience.
* Provide a library with commonly used features such as HTTP requests, JSON processing and SQL database support.
* Provide a clean [interface](src/Lib/ILib.php) for extending the language with your own functions defined in PHP.
* Provide a safe-mode where access to the file system and other external I/O is restricted.
* Provide a debug mode which shows what is happening inside the code evaluation.
* Loosely respect the Lisp legacy with things like naming conventions but do not be constrained by it.

## Non-goals

* Ability to call arbitrary PHP functions directly. The language should have control over which PHP functions can be called and how.
* Namespaces or similar mechanisms. The global namespace is a feature, not a bug! Use a prefix for your function names if this becomes a problem.

## Requirements

The project requires PHP 7.4 or newer.

The core project does not have any dependencies to external [Composer](https://getcomposer.org/) libraries, but it does currently use Composer for the autoloader so you need to run **composer install** for that.

## Usage

Use the **run.php** file to invoke the interpreter from the command line. You can start the Repl with the -r switch:

```
$ php run.php -r
```

You can also evaluate code directly with the -e switch:

```
$ php run.php -e "(+ 1 2 3)"
6
```

You can evaluate a file by giving it as argument:

```
$ php run.php file.mad
```

With no arguments the script will read input from stdin:

```
$ echo "(+ 1 2 3)" | php run.php
6
```

## Init file

You can create an init file in your home directory with the name `.madlisp_init`. This file is automatically executed when the interpreter is started. It is useful for registering commonly used functions and performing other initialization.

## Using from PHP

You can use the [LispFactory](src/LispFactory.php) class to create an instance of the interpreter if you wish to embed the MadLisp language in your PHP application and call it directly from your code.

### Safe-mode

The language features a safe-mode that disables functions which allow external I/O. This allows a "sandbox" to be created where the evaluated scripts do not have access to the file system or similar resources. It is intended to be used when MadLisp is used as an embedded scripting language inside another application.

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

```text
> (list 1 2 3)
(1 2 3)
```

Vectors are defined using square brackets or the built-in `vector` function:

```text
> [1 2 3]
[1 2 3]

> (vector 4 5 6)
[4 5 6]
```

Internally lists and vectors are just PHP arrays wrapped in a class, and the only difference between the two is how they are evaluated. Another reason for adding vectors is the familiarity of the square bracket syntax for PHP developers. They can be thought of as PHP arrays for most intents and purposes.

### Hash maps

Hash maps are collections of key-value pairs. Keys are normal strings, not "keywords" starting with colon characters as in many Lisp languages.

Hash maps are defined using curly brackets or using the built-in `hash` function. Odd arguments are treated as keys and even arguments are treated as values. The key-value pair can optionally include colon as a separator to make it more readable, but it is ignored internally.

```text
> (hash "a" 1 "b" 2)
{"a":1 "b":2}

> {"key":"value"}
{"key":"value"}
```

Internally hash maps are just regular associative PHP arrays wrapped in a class.

### Symbols

Symbols are words which do not match any other type and are separated by whitespace. They can contain special characters. Examples of symbols are `a`, `name` or `+`.

## Environments

Environments are hash-maps which store key-value pairs and use symbols as keys. Symbols are evaluated by looking up the corresponding value from the current environment. If the key is not defined in current environment the lookup proceeds to the parent environment and so forth. The initial environment is called `root` and contains all the built-in functions listed here. Then another environment called `user` is created for anything the user wants to define. The `let` and `fn` special forms create new local environments. Note that `def` always uses the current environment, so anything defined with `def` is not visible in the parent environment.

You can get the name of an environment using the `meta` function:

```text
> (meta (env) "name")
"root/user"
```

You can also retrieve the parent environment:

```text
> (meta (env) "parent")
{}
```

## Quoting

Use the `quote` special form to skip evaluation:

```text
> (quote (1 2 3))
(1 2 3)
```

Use the `quasiquote` special form when you need to turn on evaluation temporarily inside the quoted element. The special forms `unquote` and `unquote-splice` are available for that purpose:

```text
> (def lst (quote (2 3)))
(2 3)

> (quasiquote (1 lst 4))
(1 lst 4)
> (quasiquote (1 (unquote lst) 4))
(1 (2 3) 4)
> (quasiquote (1 (unquote-splice lst) 4))
(1 2 3 4)
```

Internally `quasiquote` expands to `cons` and `concat` functions. We can use the `quasiquote-expand` special form to test this expansion without evaluation:

```text
> (def lst (quote (2 3)))
(2 3)

> (quasiquote-expand (1 lst 4))
(cons 1 (cons (quote lst) (cons 4 ())))
> (quasiquote-expand (1 (unquote lst) 4))
(cons 1 (cons lst (cons 4 ())))
> (quasiquote-expand (1 (unquote-splice lst) 4))
(cons 1 (concat lst (cons 4 ())))
```

### Quote shortcuts

You can use the single-quote (`'`), backtick and tilde (`~`) characters as shortcuts for `quote`, `quasiquote` and `unquote` respectively:

```text
> '(a b c)
(a b c)

> `(a ~(+ 1 2) c)
(a 3 c)
```

All special forms related to quoting require exactly one argument.

## Macros

The language has support for Lisp-style macros. Macros are like preprocessor directives and allow the manipulation of the language syntax before evaluation.

There are two built-in macros: `defn` which is a shortcut for the form `(def ... (fn ...))` and `defmacro` which is a shortcut for the form `(def ... (macro ...))`.

We can use the special form `macroexpand` to test macro expansion without evaluating the resulting code. To illustrate how macros work, lets use `defn` as an example, and then view the expanded form using `macroexpand`:

```text
> (def defn (macro (name args body) (quasiquote (def (unquote name) (fn (unquote args) (unquote body))))))
<macro>
> (macroexpand (defn add (a b) (+ a b)))
(def add (fn (a b) (+ a b)))
```

For another example, lets combine `if` and `not` into a macro named `unless`, this time using a shorter syntax:

```text
> (defmacro unless (pred a b) `(if (not ~pred) ~a ~b))
<macro>
> (macroexpand (unless false "is false" "not false"))
(if (not false) "is false" "not false")
> (unless false "is false" "not false")
"is false"
```

The `quasiquote` form described above is essential for declaring macros. Internally macros are just functions with a special flag.

## Reflection

You can use the `meta` special form to retrieve the arguments, body, code or full definition of user-defined functions:

```text
> (defn add (a b) (+ a b))
<function>

> (meta add "args")
(a b)
> (meta add "body")
(+ a b)
> (meta add "code")
(fn (a b) (+ a b))
> (meta add "def")
(defn add (a b) (+ a b))
```

This allows for some fun tricks. For example, we can retrieve the body of a function and evaluate it as part of another function:

```text
> (defn addOne (n) (+ n 1))
<function>
> (defn addTwo (n) (+ n 2))
<function>
> (defn addBoth (n) (+ (eval (meta addOne "body")) (eval (meta addTwo "body"))))
<function>
> (addBoth 1)
5
```

## Special forms

Name  | Safe-mode | Example | Example result | Description
----- | --------- | ------- | -------------- | -----------
and   | yes | `(and 1 0 2)` | `0` | Return the first value that evaluates to false, or the last value.
case  | yes | `(case (= 1 0) 0 (= 1 1) 1)` | `1` | Treat odd arguments as tests and even arguments as values. Evaluate and return the value after the first test that evaluates to true.
      | yes | `(case (= 1 0) 0 "no match")` | `"no match"` | You can also give optional last argument to case which is returned if none of the tests evaluated to true.
def   | yes | `(def addOne (fn (a) (+ a 1)))` | `<function>` | Define a value in the current environment.
do    | yes | `(do (print 1) 2)` | `12` | Evaluate multiple expressions and return the value of the last.
env   | yes | `(env +)` | `<function>` | Return a definition from the current environment represented by argument. Without arguments return the current environment as a hash-map.
eval  | yes | `(eval (quote (+ 1 2)))` | `3` | Evaluate the argument.
fn    | yes | `(fn (a b) (+ a b))` | `<function>` | Create a function. Arguments can also be given as a vector instead of a list.
if    | yes | `(if (< 1 2) "yes" "no")` | `"yes"` | If the first argument evaluates to true, evaluate and return the second argument, otherwise the third argument. If the third argument is omitted return `null` in its place.
let   | yes | `(let (a (+ 1 2)) a)` | `3` | Create a new local environment using the first argument (list) to define values. Odd arguments are treated as keys and even arguments are treated as values. The last argument is the body of the let-expression which is evaluated using this new environment.
load  | no  | `(load "file.mad")` | | Read and evaluate a file. The contents are implicitly wrapped in a `do` expression.
macro | yes | | | See the section Macros.
macroexpand | yes | | | See the section Macros.
meta  | yes | | | See the sections Environments and Reflection.
or    | yes | `(or false 0 1)` | `1` | Return the first value that evaluates to true, or the last value.
quote | yes | | | See the section Quoting.
quasiquote | yes | | | See the section Quoting.
quasiquote-expand | yes | | | See the section Quoting.
undef | yes | `(undef myFn)` | `<function>` | Remove a definition from the current environment. Return the removed value.

## Functions

### Core functions

Name  | Safe-mode | Example | Example result | Description
----- | --------- | ------- | -------------- | -----------
debug | no  | `(debug)` | `true` |  Toggle debug output.
doc   | yes | `(doc +)` | `"Return the sum of all arguments."` | Show the documentation string for a function.
      | yes | `(doc myfn "Documentation string.")` | `"Documentation string."` | Set the documentation string for a function.
error | yes | `(error "invalid value")` | `error: invalid value` | Throw an exception with message as argument.
exit  | no  | `(exit 1)` | | Terminate the script with given exit code using [exit](https://www.php.net/manual/en/function.exit.php).
loop  | yes | `(loop (fn (a) (do (print a) (coinflip))) "hello ")` | `hello hello hello false` | Call the given function repeatedly in a loop until it returns false.
print | no  | `(print "hello world")` | `hello world` | Print expression on the screen. Give optional second argument as `true` to show strings in readable format. Print returns null (shown in REPL).
pstr  | yes | `(pstr {"a":"b"})` | `"{\"a\":\"b\"}"` | Print expression to string.
read  | yes | `(read "(+ 1 2 3)")` | `(+ 1 2 3)` | Read a string as code and return the expression.
sleep | no  | `(sleep 2000)` | `null` | Sleep for the given period given in milliseconds using [usleep](https://www.php.net/manual/en/function.usleep).
timer | no  | `(timer (fn (d) (sleep d)) 200)` | `0.20010209` | Measure the execution time of a function and return it in seconds.

### Collection functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
hash    | `(hash "a" 1 "b" 2)` | `{"a":1 "b":2}` | Create a new hash-map.
list    | `(list 1 2 3)` | `(1 2 3)` | Create a new list.
vector  | `(vector 1 2 3)` | `[1 2 3]` | Create a new vector.
range   | `(range 2 5)` | `[2 3 4]` | Create a vector with integer values from first to argument (inclusive) to second argument (exclusive).
range   | `(range 5)` | `[0 1 2 3 4]` | Range can also be used with one argument in which case it is used as length for a vector of integers starting from 0.
ltov    | `(ltov '(1 2 3))` | `[1 2 3]` | Convert list to vector.
vtol    | `(vtol [1 2 3])` | `(1 2 3)` | Convert vector to list.
empty?  | `(empty? [])` | `true` | Return true if collection is empty, otherwise false.
get     | `(get [1 2 3] 0)` | `1` | Return the nth element from a sequence, or the corresponding value for the given key from a hash-map.
len     | `(len [1 2 3])` | `3` | Return the number of elements in a collection.
first   | `(first [1 2 3 4])` | `1` | Return the first element of a sequence.
second  | `(second [1 2 3 4])` | `2` | Return the second element of a sequence.
penult  | `(penult [1 2 3 4])` | `3` | Return the second-last element of a sequence.
last    | `(last [1 2 3 4])` | `4` | Return the last element of a sequence.
head    | `(head [1 2 3 4])` | `[1 2 3]` | Return new sequence which contains all elements except the last.
tail    | `(tail [1 2 3 4])` | `[2 3 4]` | Return new sequence which contains all elements except the first.
slice   | `(slice [1 2 3 4 5] 1 3)` | `[2 3 4]` | Return a slice of the sequence using offset and length. Uses [array_slice](https://www.php.net/manual/en/function.array-slice.php).
apply   | `(apply + 1 2 [3 4])` | `10` | Call the first argument using a sequence as argument list. Intervening arguments are prepended to the list.
chunk   | `(chunk [1 2 3 4 5] 2)` | `[[1 2] [3 4] [5]]` | Divide a sequence to multiple sequences with specified length using [array_chunk](https://www.php.net/manual/en/function.array-chunk.php).
concat  | `(concat [1 2] '(3 4))` | `(1 2 3 4)` | Concatenate multiple sequences together and return them as a list.
push    | `(push [1 2] 3 4)` | `[1 2 3 4]` | Create new sequence by inserting arguments at the end.
cons    | `(cons 1 2 [3 4])` | `[1 2 3 4]` | Create new sequence by inserting arguments at the beginning.
map     | `(map (fn (a) (* a 2)) [1 2 3])` | `[2 4 6]` | Create new sequence by calling a function for each item. Uses [array_map](https://www.php.net/manual/en/function.array-map.php) internally.
map2    | `(map2 + [1 2 3] [4 5 6])` | `[5 7 9]` | Create new sequence by calling a function for each item from both sequences.
reduce  | `(reduce + [2 3 4] 1)` | `10` | Reduce a sequence to a single value by calling a function sequentially of all arguments. Optional third argument is used to give the initial value for first iteration. Uses [array_reduce](https://www.php.net/manual/en/function.array-reduce.php) internally.
filter  | `(filter odd? [1 2 3 4 5])` | `[1 3 5]` | Create a new sequence by using the given function as a filter. Uses [array_filter](https://www.php.net/manual/en/function.array-filter.php) internally.
filterh | `(filterh (fn (v k) (prefix? k "a")) {"aa":1 "ab":2 "bb":3})` | `{"aa":1 "ab":2}` | Same as filter but for hash-maps. First argument passed to the callback is the value and second is the key.
reverse | `(reverse [1 2 3])` | `[3 2 1]` | Reverse the order of a sequence. Uses [array_reverse](https://www.php.net/manual/en/function.array-reverse.php) internally.
key?    | `(key? {"a" "b"} "a")` | `true` | Return true if the hash-map contains the given key.
set     | `(set {"a" 1} "b" 2)` | `{"a":1 "b":2}` | Create new hash-map which contains the given key-value pair.
set!    | `(set! {"a" 1} "b" 2)` | `2` | Modify the given hash-map by setting the given key-value pair and return the set value. **This function is mutable!**
unset   | `(unset {"a":1 "b":2 "c":3} "b")` | `{"a":1 "c":3}` | Create a new hash-map with the given key removed.
unset!  | `(unset! {"a":1 "b":2 "c":3} "b")` | `2` | Modify the given hash-map by removing the given key and return the corresponding value. **This function is mutable!**
keys    | `(keys {"a" 1 "b" 2})` | `("a" "b")` | Return a list of the keys for a hash-map.
values  | `(values {"a" 1 "b" 2})` | `(1 2)` | Return a list of the values for a hash-map.
zip     | `(zip ["a" "b"] [1 2])` | `{"a":1 "b":2}` | Create a hash-map using the first sequence as keys and the second as values. Uses [array_combine](https://www.php.net/manual/en/function.array-combine.php) internally.
sort    | `(sort [6 4 8 1])` | `[1 4 6 8]` | Sort the sequence using [sort](https://www.php.net/manual/en/function.sort.php).
usort   | `(usort (fn (a b) (if (< a b) 0 1)) [3 1 5 4 2])` | `[1 2 3 4 5]` | Sort the sequence using custom comparison function using [usort](https://www.php.net/manual/en/function.usort.php).

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

### Database functions

This is a simple wrapper for [PDO](https://www.php.net/manual/en/book.pdo.php). This library is disabled in safe-mode.

Name        | Example | Example result | Description
----------- | ------- | -------------- | -----------
db-open     | `(def d (db-open "mysql:host=localhost;dbname=test" "testuser" "testpw"))` | `<object<PDO>>` | Open a database connection.
db-execute  | `(db-execute d "INSERT INTO test_table (col1, col2) values (?, ?)" [1, 2])` | `1` | Execute a SQL statement and return the number of affected rows.
db-query    | `(db-query d "SELECT * FROM test_table WHERE col1 = ?" [1])` | | Execute a SELECT statement.
db-last-id  | `(db-last-id d)` | `"1"` | Return the last id of auto-increment column.
db-trans    | `(db-trans d)` | `true` | Start a transaction.
db-commit   | `(db-commit d)` | `true` | Commit a transaction.
db-rollback | `(db-rollback d)` | `true` | Roll back a transaction.

### Http functions

This is a simple wrapper for [cURL](https://www.php.net/manual/en/book.curl.php). This library is disabled in safe-mode.

Name        | Example | Example result | Description
----------- | ------- | -------------- | -----------
http        | `(http "POST" "http://example.com/" (to-json {"key":"value"}) {"Content-Type":"application/json"})` | `{"status":200 "body":"" "headers":{}}` | Perform a HTTP request. First argument is the HTTP method, second is URL, third is request body and fourth is headers as a hash-map. The function returns a hash-map which contains keys `status`, `body` and `headers`.

### IO functions

This library is disabled in safe-mode.

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
wd      | `(wd)` | `"/home/pekka/code/madlisp/"` | Get the current working directory.
chdir   | `(chdir "/tmp")` | `true` | Change the current working directory.
file?   | `(file? "test.txt")` | `true` | Return true if the file exists.
fget    | `(fget "test.txt")` | `"content"` | Read the contents of a file using [file_get_contents](https://www.php.net/manual/en/function.file-get-contents.php).
fput    | `(fput "test.txt" "content")` | `true` | Write string to file using [file_put_contents](https://www.php.net/manual/en/function.file-put-contents.php). Give optional third parameter as `true` to append.
fopen   | `(def f (fopen "test.txt" "w"))` | `<resource>` | Open a file for reading or writing. Give the mode as second argument.
fclose  | `(fclose f)` | `true` | Close a file resource.
fwrite  | `(fwrite f "abc")` | `3` | Write to a file resource.
fflush  | `(fflush f)` | `true` | Persist buffered writes to disk for a file resource.
fread   | `(fread f 16)` | `"abc"` | Read from a file resource.
feof?   | `(feof? f)` | `true` | Return true if end of file has been reached for a file resource.
readline | `(readline "What is your name? ")` | `What is your name? ` | Read line of user input using [readline](https://www.php.net/manual/en/function.readline.php).
readline-add | `(readline-add "What is your name? ")` | `true` | Add line of user input to readline history using [readline_add_history](https://www.php.net/manual/en/function.readline-add-history.php).
readline-load | `(readline-load "historyfile")` | `true` | Read readline history from file using [readline_read_history](https://www.php.net/manual/en/function.readline-read-history.php).
readline-save | `(readline-save "historyfile")` | `true` | Write readline history into file using [readline_write_history](https://www.php.net/manual/en/function.readline-write-history.php).

### Json functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
to-json | `(to-json { "a" [1 2 3] "b" [4 5 6] })` | `"{\"a\":[1,2,3],\"b\":[4,5,6]}"` | Encode the argument as a JSON string.
from-json | `(from-json "{\"a\":[1,2,3],\"b\":[4,5,6]}")` | `{"a":[1 2 3] "b":[4 5 6]}` | Decode the JSON string given as argument.

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
coinflip | `(coinflip)` | `true` | Return true or false with equal probability.
rand  | `(rand 5 10)` | `8` | Return a random integer between given min and max values.

### Regular expression functions

Name          | Example | Example result | Description
------------- | ------- | -------------- | -----------
re-match      | `(re-match "/^[a-z]{4}[0-9]{4}$/" "test1234")` | `true` | Match subject to regular expression using [preg_match](https://www.php.net/manual/en/function.preg-match.php).
re-match      | `(re-match "/[a-z]{5}/" "one three five" true)` | `"three"` | Give true as third argument to return the matched text.
re-match-all  | `(re-match-all "/[A-Z][a-z]{2}[0-9]/" "One1 Two2 Three3")` | `["One1" "Two2"]` | Find multiple matches to regular expression using [preg_match_all](https://www.php.net/manual/en/function.preg-match-all.php).
re-replace    | `(re-replace "/year ([0-9]{4}) month ([0-9]{2})/" "$1-$2-01" "year 2020 month 10")` | `"2020-10-01"` | Perform search and replace with regular expression using [preg_replace](https://www.php.net/manual/en/function.preg-replace.php).
re-split      | `(re-split "/\\s+/" "aa   bb   cc   ")` | `["aa" "bb" "cc"]` | Split the subject by regular expression using [preg_split](https://www.php.net/manual/en/function.preg-split.php). The flag `PREG_SPLIT_NO_EMPTY` is enabled.

### String functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
empty?  | `(empty? "")` | `true` | Return true if argument is empty string.
len     | `(len "hello world")` | `11` | Return the length of a string using [strlen](https://www.php.net/manual/en/function.strlen.php).
trim    | `(trim " abc ")` | `"abc"` | Trim the string using [trim](https://www.php.net/manual/en/function.trim).
upcase  | `(upcase "abc")` | `"ABC"` | Make the string upper case using [strtoupper](https://www.php.net/manual/en/function.strtoupper).
lowcase | `(lowcase "Abc")` | `"abc"` | Make the string lower case using [strtolower](https://www.php.net/manual/en/function.strtolower.php).
substr  | `(substr "hello world" 3 5)` | `"lo wo"` | Get a substring using [substr](https://www.php.net/manual/en/function.substr.php).
replace | `(replace "hello world" "hello" "bye")` | `"bye world"` | Replace substrings using [str_replace](https://www.php.net/manual/en/function.str-replace.php).
split   | `(split "-" "a-b-c")` | `["a" "b" "c"]` | Split string using [explode](https://www.php.net/manual/en/function.explode.php).
join    | `(join "-" "a" "b" "c")` | `"a-b-c"` | Join string together using [implode](https://www.php.net/manual/en/function.implode.php).
format  | `(format "%d %.2f" 56 4.5)` | `"56 4.50"` | Format string using [sprintf](https://www.php.net/manual/en/function.sprintf.php).
prefix? | `(prefix? "hello world" "hello")` | `true` | Return true if the first argument starts with the second argument.
suffix? | `(suffix? "hello world" "world")` | `true` | Return true if the first argument ends with the second argument.

Note that support for multibyte characters in strings is limited because the provided functions do not use the [mbstring](https://www.php.net/manual/en/book.mbstring.php) extension.

### Time functions

Name    | Example | Example result | Description
------- | ------- | -------------- | -----------
time | `(time)` | `1592011969` | Return the current unix timestamp using [time](https://www.php.net/manual/en/function.time).
date | `(date "Y-m-d H:i:s")` | `"2020-06-13 08:33:29"` | Format the current time and date using [date](https://www.php.net/manual/en/function.date.php).
strtotime | `(strtotime "2020-06-13 08:34:47")` | `1592012087` | Parse datetime string into unix timestamp using [strtotime](https://www.php.net/manual/en/function.strtotime.php).

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
macro? | Return true if the argument is a macro.
list? | Return true if the argument is a list.
vector? | Return true if the argument is a vector.
seq? | Return true if the argument is a sequence (list or vector).
hash? | Return true if the argument is a hash-map.
symbol? | Return true if the argument is a symbol.
object? | Return true if the argument is an object.
resource? | Return true if the argument is a resource.
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

## Constants

The following constants are defined by default:

Name     | Value
-------- | ------------
DIRSEP   | PHP constant `DIRECTORY_SEPARATOR`
HOME     | PHP constant `$_SERVER['HOME']`
EOL      | PHP constant `PHP_EOL`
PI       | PHP constant `M_PI`
\_\_DIR\_\_  | Directory of a file being evaluated using the special form `load`. Otherwise null.
\_\_FILE\_\_ | Filename of a file being evaluated using the special form `load`. Otherwise null.

## Extending

The project is easy to extend because it is trivial to add new functions whether the implementation is defined on the PHP or Lisp side.

## License

[MIT](LICENSE)
