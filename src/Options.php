<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Options
{
    public bool $safemode = false;

    // Compiler optimizations:

    // Do not use Util::valueForCompare when this is true
    public bool $compileSimpleComparisons = true;
}
