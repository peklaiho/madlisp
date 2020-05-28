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
        if ($tokens[$index] == '(') {
            return $this->readList($tokens, $index);
        } else {
            return $this->readAtom($tokens, $index);
        }
    }

    private function readList(array $tokens, int &$index): MList
    {
        $result = [];

        // start tag
        $index++;

        while ($tokens[$index] != ')') {
            $result[] = $this->readForm($tokens, $index);
        }

        // end tag
        $index++;

        return new MList($result);
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
