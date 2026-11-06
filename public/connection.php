<?php
require dirname(__DIR__).'/app/bootstrap.php';
reply(['connection'=>clientInfo(),'servers'=>servers()]);
