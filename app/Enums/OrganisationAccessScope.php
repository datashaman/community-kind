<?php

namespace App\Enums;

enum OrganisationAccessScope: string
{
    case All = 'all';
    case Staff = 'staff';
    case Public = 'public';
    case Jobs = 'jobs';
    case Forms = 'forms';
    case Cache = 'cache';
    case Search = 'search';
    case SignedLinks = 'signed_links';
}
