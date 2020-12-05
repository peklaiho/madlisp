<?php
namespace MadLisp;

class Reader
{
    public function read(array $tokens)
    {
        if (empty($tokens)) {
            return null;
        }

        $index = 0;
        return $this->readForm($tokens, $index);
    }

    private function readForm(array $tokens, int &$index)
    {
        if ($tokens[$index] == "'") {
            return $this->readSpecialForm($tokens, $index, 'quote');
        } elseif ($tokens[$index] == "`") {
            return $this->readSpecialForm($tokens, $index, 'quasiquote');
        } elseif ($tokens[$index] == "~") {
            return $this->readSpecialForm($tokens, $index, 'unquote');
        } elseif ($tokens[$index] == '(') {
            return $this->readList($tokens, $index);
        } elseif ($tokens[$index] == '[') {
            return $this->readVector($tokens, $index);
        } elseif ($tokens[$index] == '{') {
            return $this->readHash($tokens, $index);
        } else {
            return $this->readAtom($tokens, $index);
        }
    }

    private function readSpecialForm(array $tokens, int &$index, string $symbol)
    {
        $index++;
        $contents = [new Symbol($symbol)];
        if ($index < count($tokens) && !in_array($tokens[$index], [')', ']', '}'])) {
            $contents[] = $this->readForm($tokens, $index);
        }
        return new MList($contents);
    }

    private function readList(array $tokens, int &$index): MList
    {
        return new MList($this->readCollection($tokens, $index, ')'));
    }

    private function readVector(array $tokens, int &$index): Vector
    {
        return new Vector($this->readCollection($tokens, $index, ']'));
    }

    private function readHash(array $tokens, int &$index): Hash
    {
        $contents = $this->readCollection($tokens, $index, '}');
        return Util::makeHash($contents);
    }

    private function readCollection(array $tokens, int &$index, string $endTag): array
    {
        $result = [];

        // start tag
        $index++;

        while ($tokens[$index] != $endTag) {
            $result[] = $this->readForm($tokens, $index);
        }

        // end tag
        $index++;

        return $result;
    }

    private function readAtom(array $tokens, int &$index)
    {
        $a = $tokens[$index++];

        if ($a === 'true') {
            return true;
        } elseif ($a === 'false') {
            return false;
        } elseif ($a === 'null') {
            return null;
        } elseif (substr($a, 0, 1) === '"') {
            // string, handle special characters
            $a = substr($a, 1, -1);
            $a = str_replace("\\\\", chr(0x7f), $a);
            $a = str_replace("\\n", "\n", $a);
            $a = str_replace("\\r", "\r", $a);
            $a = str_replace("\\\"", "\"", $a);
            return str_replace(chr(0x7f), "\\", $a);
        } elseif (is_numeric($a)) {
            if (filter_var($a, FILTER_VALIDATE_INT) !== false) {
                return intval($a);
            } else {
                return floatval($a);
            }
        } else {
            return new Symbol($a);
        }
    }
}
