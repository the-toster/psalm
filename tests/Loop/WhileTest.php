<?php
namespace Psalm\Tests\Loop;

use Psalm\Tests\Traits;

class WhileTest extends \Psalm\Tests\TestCase
{
    use Traits\InvalidCodeAnalysisTestTrait;
    use Traits\ValidCodeAnalysisTestTrait;

    /**
     * @return iterable<string,array{string,assertions?:array<string,string>,error_levels?:string[]}>
     */
    public function providerValidCodeParse()
    {
        return [
            'whileVar' => [
                '<?php
                    $worked = false;

                    while (rand(0,100) === 10) {
                        $worked = true;
                    }',
                'assertions' => [
                    '$worked' => 'bool',
                ],
            ],
            'objectValueWithTwoTypes' => [
                '<?php
                    class B {}
                    class A {
                        /** @var A|B */
                        public $parent;

                        public function __construct() {
                            $this->parent = rand(0, 1) ? new A(): new B();
                        }
                    }

                    function makeA(): A {
                        return new A();
                    }

                    $a = makeA();

                    while ($a instanceof A) {
                        $a = $a->parent;
                    }',
                'assertions' => [
                    '$a' => 'B',
                ],
            ],
            'objectValueWithInstanceofProperty' => [
                '<?php
                    class B {}
                    class A {
                        /** @var A|B */
                        public $parent;

                        public function __construct() {
                            $this->parent = rand(0, 1) ? new A(): new B();
                        }
                    }

                    function makeA(): A {
                        return new A();
                    }

                    $a = makeA();

                    while ($a->parent instanceof A) {
                        $a = $a->parent;
                    }

                    $b = $a->parent;',
                'assertions' => [
                    '$a' => 'A',
                    '$b' => 'A|B',
                ],
            ],
            'objectValueNullable' => [
                '<?php
                    class A {
                        /** @var ?A */
                        public $parent;

                        public function __construct() {
                            $this->parent = rand(0, 1) ? new A(): null;
                        }
                    }

                    function makeA(): A {
                        return new A();
                    }

                    $a = makeA();

                    while ($a) {
                        $a = $a->parent;
                    }',
                'assertions' => [
                    '$a' => 'null',
                ],
            ],
            'objectValueWithAnd' => [
                '<?php
                    class A {
                        /** @var ?A */
                        public $parent;

                        public function __construct() {
                            $this->parent = rand(0, 1) ? new A(): null;
                        }
                    }

                    function makeA(): A {
                        return new A();
                    }

                    $a = makeA();

                    while ($a && rand(0, 10) > 5) {
                        $a = $a->parent;
                    }',
                'assertions' => [
                    '$a' => 'A|null',
                ],
            ],
            'loopWithNoParadox' => [
                '<?php
                    $a = ["b", "c", "d"];
                    array_pop($a);
                    while ($a) {
                        $letter = array_pop($a);
                        if (!$a) {}
                    }',
            ],
            'noRedundantConditionInWhileAssignment' => [
                '<?php
                    class A {
                      /** @var ?int */
                      public $bar;
                    }

                    function foo(): ?A {
                      return rand(0, 1) ? new A : null;
                    }

                    while ($a = foo()) {
                      if ($a->bar) {}
                    }',
            ],
            'whileTrueWithBreak' => [
                '<?php
                    while (true) {
                        $a = "hello";
                        break;
                    }
                    while (1) {
                        $b = 5;
                        break;
                    }',
                'assertions' => [
                    '$a' => 'string',
                    '$b' => 'int',
                ],
            ],
            'whileWithNotEmptyCheck' => [
                '<?php
                    class A {
                      /** @var A|null */
                      public $a;

                      public function __construct() {
                        $this->a = rand(0, 1) ? new A : null;
                      }
                    }

                    function takesA(A $a): void {}

                    $a = new A();
                    while ($a) {
                      takesA($a);
                      $a = $a->a;
                    };',
                'assertions' => [
                    '$a' => 'null',
                ],
            ],
            'whileInstanceOf' => [
                '<?php
                    class A {
                        /** @var null|A */
                        public $parent;
                    }

                    class B extends A {}

                    $a = new A();

                    while ($a->parent instanceof B) {
                        $a = $a->parent;
                    }',
            ],
            'whileInstanceOfAndNotEmptyCheck' => [
                '<?php
                    class A {
                        /** @var null|A */
                        public $parent;
                    }

                    class B extends A {}

                    $a = (new A())->parent;

                    $foo = rand(0, 1) ? "hello" : null;

                    if (!$foo) {
                        while ($a instanceof B && !$foo) {
                            $a = $a->parent;
                            $foo = rand(0, 1) ? "hello" : null;
                        }
                    }',
            ],
            'noRedundantConditionAfterArrayAssignment' => [
                '<?php
                    $data = ["a" => false];
                    while (!$data["a"]) {
                        if (rand() % 2 > 0) {
                            $data = ["a" => true];
                        }
                    }',
            ],
            'additionSubtractionAssignment' => [
                '<?php
                    $a = 0;

                    while (rand(0, 1)) {
                        if (rand(0, 1)) {
                            $a = $a + 1;
                        } elseif ($a) {
                            $a = $a - 1;
                        }
                    }'
            ],
            'additionSubtractionInc' => [
                '<?php
                    $a = 0;

                    while (rand(0, 1)) {
                        if (rand(0, 1)) {
                            $a++;
                        } elseif ($a) {
                            $a--;
                        }
                    }',
            ],
            'invalidateBothByRefAssignments' => [
                '<?php
                    function foo(?string &$i) : void {}
                    function bar(?string &$i) : void {}

                    $c = null;

                    while (rand(0, 1)) {
                        if (!$c) {
                            foo($c);
                        } else {
                            bar($c);
                        }
                    }',
            ],
            'applyLoopConditionalAfterIf' => [
                '<?php
                    class Obj {}
                    class A extends Obj {
                        /** @var A|null */
                        public $foo;
                    }
                    class B extends Obj {}

                    function foo(Obj $node) : void {
                        while ($node instanceof A
                            || $node instanceof B
                        ) {
                            if (!$node instanceof B) {
                                $node = $node->foo;
                            }
                        }
                    }',
            ],
            'shouldBeFine' => [
                '<?php
                    class Obj {}
                    class A extends Obj {
                        /** @var A|null */
                        public $foo;
                    }
                    class B extends Obj {
                        /** @var A|null */
                        public $foo;
                    }
                    class C extends Obj {
                        /** @var A|C|null */
                        public $bar;
                    }

                    function takesA(A $a) : void {}

                    function foo(Obj $node) : void {
                        while ($node instanceof A
                            || $node instanceof B
                            || ($node instanceof C && $node->bar instanceof A)
                        ) {
                            if (!$node instanceof C) {
                                $node = $node->foo;
                            } else {
                                $node = $node->bar;
                            }
                        }
                    }',
            ],
            'comparisonAfterContinue' => [
                '<?php
                    $foo = null;
                    while (rand(0, 1)) {
                        if (rand(0, 1)) {
                            $foo = 1;
                            continue;
                        }

                        $a = rand(0, 1);

                        if ($a === $foo) {}
                    }',
            ],
            'noRedundantConditionAfterWhile' => [
                '<?php
                    $i = 5;
                    while (--$i > 0) {}
                    echo $i === 0;',
            ],
            'noRedundantConditionOnAddedSubtractedInLoop' => [
                '<?php
                    $depth = 0;
                    $position = 0;
                    while (!$depth) {
                        if (rand(0, 1)) {
                            $depth++;
                        } elseif (rand(0, 1)) {
                            $depth--;
                        }
                        $position++;
                    }'
            ],
            'variableDefinedInWhileConditional' => [
                '<?php
                    function foo() : void {
                        $pointers = ["hi"];

                        while (rand(0, 1) && 0 < ($parent = 0)) {
                            print $pointers[$parent];
                        }
                    }'
            ],
            'assingnedConditionallyReassignedToMixedInLoop' => [
                '<?php
                    function foo(array $arr): void {
                        while (rand(0, 1)) {
                            $t = true;
                            if (!empty($arr[0])) {
                                /** @psalm-suppress MixedAssignment */
                                $t = $arr[0];
                            }
                            if ($t === true) {}
                        }
                    }',
            ],
            'varChangedAfterUseInsideLoop' => [
                '<?php
                    function takesString(string $s) : void {}

                    /**
                     * @param array<string> $fields
                     */
                    function changeVarAfterUse(array $values, array $fields): void {
                        foreach ($fields as $field) {
                            if (!isset($values[$field])) {
                                continue;
                            }

                            /** @psalm-suppress MixedAssignment */
                            $value = $values[$field];

                            /** @psalm-suppress MixedArgument */
                            takesString($value);

                            $values[$field] = null;
                        }
                    }',
            ],
            'invalidateWhileAssertion' => [
                '<?php
                    function test(array $x, int $i) : void {
                        while (isset($x[$i]) && is_array($x[$i])) {
                            $i++;
                        }
                    }'
            ],
            'possiblyUndefinedInWhile' => [
                '<?php
                    function getRenderersForClass(string $a): void {
                        /** @psalm-suppress MixedArgument */
                        while ($b = getString($b ?? $a)) {
                            $c = "hello";
                        }
                    }

                    function getString(string $s) : ?string {
                        return rand(0, 1) ? $s : null;
                    }'
            ],
            'thornyLoop' => [
                '<?php

                    function searchCode(string $content, array &$tmp) : void {
                        // separer les balises du texte
                        $tmp = [];
                        $reg = \'/(<[^>]+>)|([^<]+)+/isU\';

                        // pour chaque element trouve :
                        $str    = "";
                        $offset = 0;
                        while (preg_match($reg, $content, $parse, PREG_OFFSET_CAPTURE, $offset)) {
                            $str .= "hello";
                            unset($parse);
                        }
                    }'
            ],
            'assignToTKeyedArrayListPreserveListness' => [
                '<?php
                    /**
                     * @return non-empty-list<string>
                     */
                    function foo(string $key): array {
                        $elements = [$key];

                        while (rand(0, 1)) {
                            $elements[] = $key;
                        }

                        return $elements;
                    }',
            ],
            'reconcilePositiveInt' => [
                '<?php
                    $counter = 0;

                    while (rand(0, 1)) {
                        if ($counter > 0) {
                            $counter = $counter - 1;
                        } else {
                            $counter = $counter + 1;
                        }
                    }'
            ],
        ];
    }

    /**
     * @return iterable<string,array{string,error_message:string,2?:string[],3?:bool,4?:string}>
     */
    public function providerInvalidCodeParse()
    {
        return [
            'whileTrueNoBreak' => [
                '<?php
                    while (true) {
                        $a = "hello";
                    }

                    echo $a;',
                'error_message' => 'UndefinedGlobalVariable',
            ],
            'invalidateByRefAssignmentWithRedundantCondition' => [
                '<?php
                    function foo(?string $i) : void {}
                    function bar(?string $i) : void {}

                    $c = null;

                    while (rand(0, 1)) {
                        if (!$c) {
                            foo($c);
                        } else {
                            bar($c);
                        }
                    }',
                'error_message' => 'RedundantCondition',
            ],
        ];
    }
}
