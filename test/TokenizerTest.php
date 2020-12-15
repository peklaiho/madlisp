<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\MadLispException;
use MadLisp\Tokenizer;

class TokenizerTest extends TestCase
{
    public function exceptionProvider(): array
    {
        return [
            ['"', 'unterminated string'],
            ['"\\', 'unterminated string'],
            ['"\\"', 'unterminated string'],
            ['"\\ ', "invalid escape sequence \\ "],
            ['"\\a', "invalid escape sequence \\a"],
            ['(', 'missing closing )'],
            ['[', 'missing closing ]'],
            ['{', 'missing closing }'],
            ['(()', 'missing closing )'],
            ['[[]', 'missing closing ]'],
            ['{{}', 'missing closing }'],
            [')', 'unexpected closing )'],
            [']', 'unexpected closing ]'],
            ['}', 'unexpected closing }'],
            ['())', 'unexpected closing )'],
            ['[]]', 'unexpected closing ]'],
            ['{}}', 'unexpected closing }'],
        ];
    }

    /**
     * Test inputs that throw an exception.
     * @dataProvider exceptionProvider
     */
    public function testException(string $input, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        $tokenizer = new Tokenizer();
        $tokenizer->tokenize($input);
    }

    public function tokenProvider(): array
    {
        return [
            // Ignored characters
            ["", []],
            [" ", []],
            ["\t", []],
            ["\n", []],
            ["\r", []],
            [":", []],
            [" \t\n\r: ", []],
            [" aa\t\n\rbb:\r\ncc\t ", ['aa', 'bb', 'cc']],

            // Comments
            [";comment", []],
            ["a;c(o[m{m}e]n)t\nb", ['a', 'b']], // parens inside comment
            ["a;com\"ment\nb", ['a', 'b']], // quote inside comment
            ["a;comment\rb", ['a', 'b']], // end with \r
            ["a;;comment\nb", ['a', 'b']], // two ;
            ["a;com\nb;ment\rc", ['a', 'b', 'c']], // two comments

            // Parens
            ["aa(bb[cc{dd}ee]gg)ff", ['aa', '(', 'bb', '[', 'cc', '{', 'dd', '}', 'ee', ']', 'gg', ')', 'ff']],
            // Special characters: '`~
            ["aa'bb`cc~dd~ee`gg'ff", ['aa', "'", 'bb', '`', 'cc', '~', 'dd', '~', 'ee', '`', 'gg', "'", 'ff']],
            // Other non-alphabet characters are symbols
            ["(aa!@#$%^&*-_=+bb<>,./?\\|cc)", ['(', "aa!@#$%^&*-_=+bb<>,./?\\|cc", ')']],

            // Strings
            ['"abc"', ['"abc"']],
            ['aa"bb"cc', ['aa', '"bb"', 'cc']],
            ['aa"bb;cc"dd', ['aa', '"bb;cc"', 'dd']], // comment inside string
            ['aa"bb""cc"dd', ['aa', '"bb"', '"cc"', 'dd']], // two strings
            ["aa\"bb\\\"cc\"dd", ['aa', "\"bb\"cc\"", 'dd']], // quote inside string
            ["aa\"bb\n\rcc\"dd", ['aa', "\"bb\n\rcc\"", 'dd']], // linebreaks inside string
            ["aa\"bb\\n\\r\\tcc\"dd", ['aa', "\"bb\n\r\tcc\"", 'dd']], // escaped linebreaks
            ["aa\"bb\\\\n\\\\rcc\"dd", ['aa', "\"bb\\n\\rcc\"", 'dd']], // escaped backslashes
            ["aa\"bb\\\\\"cc", ['aa', "\"bb\\\"", 'cc']],
            ["aa\"bb\\\\\\\"cc\"dd", ['aa', "\"bb\\\"cc\"", 'dd']],

            // Test everything together
            ["(abc<+=-_!?>\"str\n\\r;\\\"\";com\"ment\r{\"a\":\"b\"})", ['(', 'abc<+=-_!?>', "\"str\n\r;\"\"", '{', '"a"', '"b"', '}', ')']],
        ];
    }

    /**
     * Test valid inputs.
     * @dataProvider tokenProvider
     */
    public function testTokenize(string $input, array $expected)
    {
        $tokenizer = new Tokenizer();
        $result = $tokenizer->tokenize($input);
        $this->assertSame($expected, $result);
    }
}