<?php

str_contains('foobar', 'foo');
sprintf('%s/%s', 'foo', 'bar');

?>
-----
<?php

str_contains(haystack: 'foobar', needle: 'foo');
sprintf('%s/%s', 'foo', 'bar');

?>
