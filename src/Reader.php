<?php
namespace MadLisp;

class Reader
{
    public function read(array $tokens): array
    {
        $result = [];
        $index = 0;

        while ($index < count($tokens)) {
            $result[] = $this->readForm($tokens, $index);
        }

        return $result;
    }

    private function readForm(array $tokens, int &$index)
    {
        if ($tokens[$index] == "'") {
            $index++;
            $contents = [new Symbol('quote')];
            if ($index < count($tokens) && !in_array($tokens[$index], [')', ']', '}'])) {
                $contents[] = $this->readForm($tokens, $index);
            }
            return new MList($contents);
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
            // string
            return substr($a, 1, strlen($a) - 2);
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
