<?php

preg_split('/,/', 'a,b', -1, PREG_SPLIT_NO_EMPTY);
?>
-----
<?php

preg_split(pattern: '/,/', subject: 'a,b', flags: PREG_SPLIT_NO_EMPTY);
?>
