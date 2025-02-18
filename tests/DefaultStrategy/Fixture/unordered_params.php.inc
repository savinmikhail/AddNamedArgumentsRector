<?php

function foo(string $foo = 'foo', string $bar = 'bar', string $baz = 'baz'): void {}

foo(bar: 'barr', baz: 'bazz');
?>
-----
<?php

function foo(string $foo = 'foo', string $bar = 'bar', string $baz = 'baz'): void {}

foo(bar: 'barr', baz: 'bazz');
?>
