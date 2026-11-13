<?php

namespace App\Enums;

enum AccountKind: string
{
    case Page = 'page';
    case Profile = 'profile';
    case Channel = 'channel';
    case Organization = 'organization';
    case Business = 'business';
}
