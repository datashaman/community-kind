<?php

return [
    'vulnerability_contact' => env(
        'SECURITY_CONTACT',
        'https://github.com/datashaman/community-kind/security/advisories/new',
    ),
    'security_txt_expires' => env('SECURITY_TXT_EXPIRES', '2027-08-30T00:00:00Z'),
];
