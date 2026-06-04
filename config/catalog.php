<?php

return [
    'meta_enabled' => filter_var(env('META_CATALOG', true), FILTER_VALIDATE_BOOLEAN),
];
