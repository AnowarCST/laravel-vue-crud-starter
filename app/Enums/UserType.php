<?php

namespace App\Enums;

/**
 * The account type, stored in users.type. Only administrators may manage
 * accounts, delete catalogue entries and (outside the local environment)
 * open the API documentation.
 */
enum UserType: string
{
    case Admin = 'admin';
    case User = 'user';
}
