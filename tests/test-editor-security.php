<?php

declare(strict_types=1);

// Exercise the actual editor callbacks, DOM construction and rendered element props.
passthru('node ' . escapeshellarg(__DIR__ . '/editor-security.js'), $status);
exit($status);
