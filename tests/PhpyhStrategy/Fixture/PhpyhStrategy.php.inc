<?php

function oneParam(string $foo): void {}
function twoParamWithSameType(string $foo, string $bar): void {}
function twoParamWithDifferentTypes(string $foo, int $bar): void {}
function threeParam(string $foo, string $bar, float $baz): void {}
function threeParamWithOneRequired(string $foo, string $bar = '', float $baz = 0): void {}

oneParam('foo');
twoParamWithSameType('foo', 'bar');
twoParamWithDifferentTypes('foo', 23);
threeParam('foo', 'bar', 8.3);
///** @no-named-arguments  */
//threeParam('foo', 'bar', 8.3);
//threeParamWithOneRequired('foo');
-----
<?php

function oneParam(string $foo): void {}
function twoParamWithSameType(string $foo, string $bar): void {}
function twoParamWithDifferentTypes(string $foo, int $bar): void {}
function threeParam(string $foo, string $bar, float $baz): void {}
function threeParamWithOneRequired(string $foo, string $bar = '', float $baz = 0): void {}

oneParam('foo');
twoParamWithSameType(foo: 'foo', bar: 'bar');
twoParamWithDifferentTypes('foo', 23);
threeParam(foo: 'foo', bar: 'bar', baz: 8.3);
///** @no-named-arguments  */
//threeParam('foo', 'bar', 8.3);
//threeParamWithOneRequired('foo');