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
    case Files = 'files';
    case Exports = 'exports';
    case Reports = 'reports';
    case Commands = 'commands';
    case Audits = 'audits';
    case SignedLinks = 'signed_links';
}
