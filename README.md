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

Use the **run.php** file to invoke the interpreter from the command line. You can start the REPL with the -r switch:

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

The language includes a safe-mode that disables functions which allow external I/O. This allows a "sandbox" to be created where the evaluated scripts do not have access to the file system or other resources.

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

Lists are limited by parenthesis. They can be defined using the built-in `list` function:

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

Symbols are evaluated by looking up the corresponding value from the current environment.

## Functions

Functions are created using the `fn` special form, also known as `lambda` in other Lisp languages:

```text
> (fn (a b) (+ a b))
<function>
```

The first argument to `fn` is a list of bindings which are used as arguments to the created function. The second argument is the function body.

A function is applied or "called" when a list is evaluated. The function is the first item of the list and the remaining items are arguments to the function. When a function is applied, a new environment is created where the bindings are bound to the given arguments, and the function body is then evaluated in this new environment.

We can apply the above function directly by putting it inside a list and giving it some arguments:

```text
> ((fn (a b) (+ a b)) 1 2)
3
```

More commonly we define a function in the environment first, essentially giving it a "name", and then apply it separately:

```text
> (def add (fn (a b) (+ a b)))
<function>
> (add 1 2)
3
```

Note that trying to evaluate a list which does not contain a function as the first item is an error:

```text
> ("string" 1 2)
error: eval: first item of list is not function
```

Finally, the bindings to `fn` can be given as a vector, if that syntax is preferred:

```text
> (fn [a b] (+ a b))
<function>
```

## Environments

Environments are hash-maps which store key-value pairs and use symbols as keys. If the key is not defined in current environment the lookup proceeds to the parent environment and so forth. The initial environment is called `root` and contains all the built-in functions listed here. Another environment called `user` is created for anything the user wants to define.

You can define values in the environment using `def`:

```text
> (def abc 123)
123
> abc
123
> (def addOne (fn (a) (+ a 1)))
<function>
> (addOne abc)
124
```

Note that `def` always uses the current environment, so anything defined with `def` is not visible in the parent environment.

You can retrieve the current environment using `env`:

```text
> (env)
{"abc":123 "addOne":<function>}
```

You can remove a definition from the current environment using `undef`:

```text
> (undef addOne)
<function>
```

You can get the name of an environment and the parent environment using the `meta`:

```text
> (meta (env) "name")
"root/user"
> (meta (env) "parent")
{}
```

### Let

You can create a new environment using `let`. It is useful for "local variables":

```text
> (let (a 1 b 2) (+ a b))
3
```

The first argument to let is a list of bindings defined in the new environment. In this example the value of `a` is set to 1, and the value of `b` to 2. Then the body expression, `(+ a b)` in the example, is evaluated in the new environment.

The body of `let` can contain multiple expressions and the value of the whole expression is the value of the last expression:

```text
> (let (a 1 b 2) (print "Number is: ") (+ a b))
Number is: 3
```

The values of previous bindings can be used in subsequent bindings:

```text
> (let (a (+ 1 2) b (* a 2)) b)
6
```

Finally, the bindings can be given as a vector, if that syntax is preferred:

```text
> (let [a 1 b 2] (+ a b))
3
```

## Control flow

### Do

You can evaluate multiple expressions together using `do`:

```text
> (do (print "Number: ") (+ 1 2))
Number: 3
```

The value of the whole expression is the value of the last expression.

### If

Conditional evaluation is accomplished with the `if` expression which is of the form `(if test consequent alternate)`. If *test* evaluates to truthy value, *consequent* is evaluated and returned. If *test* evaluates to falsy value, *alternate* is evaluated and returned:

```text
> (if (< 1 2) "yes" "no")
"yes"
```

If *alternate* is not provided, null is returned in its place:

```text
> (if (str? 1) "string")
null
```

### And, or

The `and` form returns the first expression that evaluates to falsy value:

```text
> (and 2 true "str" 0 3)
0
```

The `or` form returns the first expression that evaluates to truthy value:

```text
> (or 0 false 3 5)
3
```

Without arguments `and` and `or` return true and false respectively:

```text
> (and)
true
> (or)
false
```

### Cond, case and case-strict

When you have more than two possible paths of execution, it is convenient to use the `cond` and `case` forms.

Consider the following defined for these examples:

```text
> (def n 4)
4
```

For `cond`, the first item of each argument is evaluated. If it evaluates to truthy value, the following expression is evaluated and returned:

```text
> (cond ((= n 2) "two") ((= n 4) "four") ((= n 6) "six"))
"four"
```

For `case`, the first argument is evaluated, and then it is matched against the first item of the remaining arguments. If there is a match, the following expression is evaluated and returned:

```text
> (case (% n 2) (0 "even") (1 "odd"))
"even"
```

Note that the values to match against, `0` and `1` in the above example, are not evaluated.

The `case-strict` is similar, but uses strict comparison:

```text
> (case n ("4" "string") (4 "integer"))
"string"
> (case-strict n ("4" "string") (4 "integer"))
"integer"
```

Both `cond` and `case` can have an `else` form which is matched if nothing else matched up to that point:

```text
> (cond ((< n 2) "small") (else "big"))
"big"
> (case (% n 2) (1 "odd") (else "even"))
"even"
```

Both `cond` and `case` can have more than one expression which is evaluated after a successful match:

```text
> (cond ((int? n) (print "Number: ") n))
Number: 4
```

The arguments to `cond` and `case` can be also be given as vectors:

```text
> (cond [(int? n) "integer"] [else "other"])
"integer"
```

If no match is found, and `else` is not defined, `cond` and `case` return null.

### While

Looping is accomplished with the `while` expression which is of the form `(while test expr1 expr2 ...)`. The *test* is evaluated at the beginning of each iteration and if it returns truthy value, the remaining expressions are evaluated. The value of the whole expression is the value of the last evaluated sub-expression.

```text
> (let (i 5) (while (> i 0) (print i) (def i (dec i))))
543210
```

Although the above example illustrates how to use `while`, this type of code is discouraged. Generally it is recommended to use recursion instead of iteration in these type of scenarios. Usually it results in cleaner code as well. The `while` expression is better suited for something like the main loop of a program.

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

There are two built-in macros: `defn` which is a shortcut for the form `(def ... (fn ...))` and `defmacro` which is a shortcut for the form `(def ... (macro ...))`. To illustrate how macros work, lets look at the definition of `defn`:

```text
(def defn (macro (name args body) (quasiquote (def (unquote name) (fn (unquote args) (unquote body))))))
```

We can use the special form `macroexpand` to test macro expansion without evaluating the resulting code:

```text
> (macroexpand (defn add (a b) (+ a b)))
(def add (fn (a b) (+ a b)))
```

For another example, lets combine `if` and `not` into a macro named `unless`, this time using a shorter syntax:

```text
> (defmacro unless (pred a b) `(if (not ~pred) ~a ~b))
<macro>
> (unless 0 "zero" "non-zero")
"zero"
> (macroexpand (unless 0 "zero" "non-zero"))
(if (not 0) "zero" "non-zero")
```

The `quasiquote` form described above is essential for declaring macros. Internally macros are just functions with a special flag.

## Exceptions

The language has support for `try-catch` style exception handlers. The syntax is `(try A (catch B C))` where A is evaluated first and if exception is thrown, then C is evaluated with the symbol B bound to the value of the exception. Exceptions are thrown using the `throw` core function. You can give any data structure as argument to `throw` and it is passed along to `catch`. This way exceptions can contain more data than just a string that represents an error message.

Simple example of throwing and catching an exception:

```
> (try (throw {"msg":"message"}) (catch ex (str "error: " (get ex "msg"))))
"error: message"
```

Exceptions generated by PHP are catched as well. Their value will be a hash-map with keys `type`, `file`, `line` and `message`:

```
> (try (get "wrong" "key") (catch ex (get ex "type")))
"TypeError"
```

The REPL contains its own exception handler defined in PHP that will catch any exceptions thrown outside of `try-catch` form.

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

Name  | Safe-mode | Described in sections
----- | --------- | ---------------------
and   | yes | Control flow
case  | yes | Control flow
case-strict | yes | Control flow
cond  | yes | Control flow
def   | yes | Environments
do    | yes | Control flow
env   | yes | Environments
eval  | yes |
fn    | yes | Functions
if    | yes | Control flow
let   | yes | Environments
load  | no  |
macro | yes | Macros
macroexpand | yes | Macros
meta  | yes | Environments, Reflection
or    | yes | Control flow
quote | yes | Quoting
quasiquote | yes | Quoting
quasiquote-expand | yes | Quoting
try   | yes | Exceptions
undef | yes | Environments
while | yes | Control flow

## Built-in functions

### Core functions

Name   | Safe-mode | Example | Example result | Description
------ | --------- | ------- | -------------- | -----------
debug  | no  | `(debug)` | `true` |  Toggle debug output.
doc    | yes | `(doc +)` | `"Return the sum of all arguments."` | Show the documentation string for a function.
       | yes | `(doc myfn "Documentation string.")` | `"Documentation string."` | Set the documentation string for a function.
exit   | no  | `(exit 1)` | | Terminate the script with given exit code using [exit](https://www.php.net/manual/en/function.exit.php).
print  | no  | `(print "hello world")` | `hello world` | Print expression on the screen.
printr | no  | `(printr "hello world")` | `"hello world"` | Print expression on the screen in readable format.
prints | yes | `(prints "hello world")` | `"\"hello world\""` | Print expression to string in readable format.
read   | yes | `(read "(+ 1 2 3)")` | `(+ 1 2 3)` | Read a string as code and return the expression.
sleep  | no  | `(sleep 2000)` | `null` | Sleep for the given period given in milliseconds using [usleep](https://www.php.net/manual/en/function.usleep).
throw  | yes | `(throw "invalid value")` | `error: "invalid value"` | Throw an exception. The given value is passed to catch. See the section Exceptions.

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
rand  | `(rand 5 10)` | `8` | Return a random integer between given min and max values.
randf | `(randf)` | `0.678` | Return a random float between 0 (inclusive) and 1 (exclusive).
rand-seed | `(rand-seed 256)` | `256` | Seed the random number generator with the given value.

### Regular expression functions

Name          | Example | Example result | Description
------------- | ------- | -------------- | -----------
re-match      | `(re-match "/^[a-z]{4}[0-9]{4}$/" "test1234")` | `true` | Match subject to regular expression using [preg_match](https://www.php.net/manual/en/function.preg-match.php).
              | `(re-match "/[a-z]{5}/" "one three five" true)` | `"three"` | Give true as third argument to return the matched text.
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
mtime | `(mtime)` | `1607696761.132` | Return the current unix timestamp as float that includes microseconds. Uses [microtime](https://www.php.net/manual/en/function.microtime).
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
