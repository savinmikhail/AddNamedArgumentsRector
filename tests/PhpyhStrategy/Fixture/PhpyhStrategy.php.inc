<?php

function phpyh_strategy(string $hardcore): void {}
function phpyh_strategy1(string $hardcore, string $course): void {}
function phpyh_strategy2(string $hardcore, int $lynch): void {}
function phpyh_strategy3(string $hardcore, string $course, float $php): void {}

phpyh_strategy('4');
phpyh_strategy1('4', '1');
phpyh_strategy2('4', 23);
phpyh_strategy3('4', '1', '8.3');
-----
<?php

function phpyh_strategy(string $hardcore): void {}
function phpyh_strategy1(string $hardcore, string $course): void {}
function phpyh_strategy2(string $hardcore, int $lynch): void {}
function phpyh_strategy3(string $hardcore, string $course, float $php): void {}

phpyh_strategy('4');
phpyh_strategy1(hardcore: '4', course: '1');
phpyh_strategy2('4', 23);
phpyh_strategy3(hardcore: '4', course: '1', php: '8.3');
