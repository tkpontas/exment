<?php

// Published by Exment (tag: lang_vendor) into resources/lang/en/validation.php

$vendorValidationPath = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

$defaults = file_exists($vendorValidationPath)
    ? require $vendorValidationPath
    : [];

return array_replace_recursive($defaults, [
    // Exment custom validator rule (uniqueInTable -> unique_in_table)
    'unique_in_table' => 'The :attribute has already been taken.',
]);
