<?php

final readonly class VariadicRunner
{
    public function run(string $command, ...$args): void
    {
    }
}

(new VariadicRunner())->run('php', '--version', '--ansi');

?>
-----
<?php

final readonly class VariadicRunner
{
    public function run(string $command, ...$args): void
    {
    }
}

(new VariadicRunner())->run(command: 'php', args1: '--version', args2: '--ansi');

?>
